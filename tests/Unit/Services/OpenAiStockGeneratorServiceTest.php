<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    private const ENDPOINT = 'https://api.openai.com/v1';
    private const COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';

    private function service(): OpenAiStockGeneratorService
    {
        return new OpenAiStockGeneratorService(self::ENDPOINT, 'mock-key', 'gpt-4o-mini');
    }

    public function test_empty_prompt_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt tidak boleh kosong.');
        $this->service()->generateStocks('   ');
    }

    public function test_missing_api_key_throws_exception_without_calling_api(): void
    {
        Http::fake();
        config(['openai.key' => '']);

        try {
            (new OpenAiStockGeneratorService(self::ENDPOINT))->generateStocks('Stok gudang');
            $this->fail('Expected RuntimeException for empty OPENAI_KEY');
        } catch (RuntimeException $e) {
            $this->assertEquals('OPENAI_KEY belum diisi di file .env.', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_successful_generation(): void
    {
        Http::fake([
            self::COMPLETIONS_URL => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'stocks' => [
                                [
                                    'name' => 'Kertas HVS A4',
                                    'sku' => 'ATK-HVS',
                                    'description' => 'Kertas 80gsm',
                                    'quantity' => 40,
                                    'unit' => 'rim',
                                    'price' => 55000.555,
                                    'min_stock' => 10,
                                ],
                                [
                                    'name' => 'Spidol',
                                    'sku' => '',
                                    'description' => null,
                                    'quantity' => -4,
                                    'unit' => ['invalid'],
                                    'price' => 'gratis',
                                    'min_stock' => 'banyak',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $results = $this->service()->generateStocks('Buatkan 2 stok ATK', 2);

        $this->assertCount(2, $results);
        $this->assertEquals('Kertas HVS A4', $results[0]->name);
        $this->assertEquals('ATK-HVS', $results[0]->sku);
        $this->assertEquals('Kertas 80gsm', $results[0]->description);
        $this->assertEquals(40, $results[0]->quantity);
        $this->assertEquals('rim', $results[0]->unit);
        $this->assertEquals(55000.56, $results[0]->price);
        $this->assertEquals(10, $results[0]->minStock);

        $this->assertEquals('Spidol', $results[1]->name);
        $this->assertNull($results[1]->sku);
        $this->assertNull($results[1]->description);
        $this->assertEquals(0, $results[1]->quantity);
        $this->assertEquals('pcs', $results[1]->unit);
        $this->assertNull($results[1]->price);
        $this->assertEquals(5, $results[1]->minStock);

        Http::assertSent(function (HttpRequest $request) {
            return $request->hasHeader('Authorization', 'Bearer mock-key')
                && $request['model'] === 'gpt-4o-mini'
                && str_contains($request['messages'][0]['content'], 'inventory');
        });
    }

    public function test_api_failure_throws_exception(): void
    {
        Http::fake([
            self::COMPLETIONS_URL => Http::response(['error' => ['message' => 'Invalid API Key provided']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Invalid API Key provided');
        $this->service()->generateStocks('Stok gudang');
    }

    public function test_api_failure_with_array_wrapped_error(): void
    {
        Http::fake([
            self::COMPLETIONS_URL => Http::response([['error' => ['message' => 'This model is currently experiencing high demand.']]], 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: This model is currently experiencing high demand.');
        $this->service()->generateStocks('Stok gudang');
    }

    public function test_api_failure_without_error_message(): void
    {
        Http::fake([
            self::COMPLETIONS_URL => Http::response('Server down', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Request failed with status code 500');
        $this->service()->generateStocks('Stok gudang');
    }

    public function test_parse_content_with_markdown_fences_and_list(): void
    {
        $raw = "```json\n[{\"name\": \"Barang Fence\", \"quantity\": 3}]\n```";

        $results = $this->service()->parseContent($raw);
        $this->assertCount(1, $results);
        $this->assertEquals('Barang Fence', $results[0]->name);
        $this->assertEquals(3, $results[0]->quantity);
    }

    public function test_parse_content_supports_items_key(): void
    {
        $results = $this->service()->parseContent('{"items": [{"name": "Barang Items"}]}');
        $this->assertCount(1, $results);
        $this->assertEquals('Barang Items', $results[0]->name);
    }

    public function test_parse_content_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI mengembalikan format respon yang tidak valid.');
        $this->service()->parseContent('Not valid JSON');
    }

    public function test_parse_content_empty_stocks(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $this->service()->parseContent('{"stocks": []}');
    }

    public function test_parse_content_unknown_object_shape(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $this->service()->parseContent('{"foo": "bar"}');
    }

    public function test_parse_content_no_valid_items(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format item stok yang dikembalikan AI tidak sesuai spesifikasi.');
        $this->service()->parseContent('{"stocks": [{"bad_key": "val"}, "string-item"]}');
    }

    public function test_constructor_handles_swapped_endpoint_and_key(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        // Key given as URL, endpoint given as secret
        $service = new OpenAiStockGeneratorService('sk-secret-key', 'https://ai.example.com/v1');
        $this->assertCount(1, $service->generateStocks('Stok'));
    }

    public function test_constructor_auto_prefixes_https_and_uses_config(): void
    {
        config(['openai.model' => 'config-model']);

        Http::fake([
            self::COMPLETIONS_URL => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        $service = new OpenAiStockGeneratorService('api.openai.com/v1', 'mock-key');
        $this->assertCount(1, $service->generateStocks('Stok'));

        Http::assertSent(fn (HttpRequest $request) => $request['model'] === 'config-model');
    }
}
