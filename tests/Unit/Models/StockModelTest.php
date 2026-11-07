<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $stock->user);
        $this->assertEquals($user->id, $stock->user->id);
    }

    public function test_stock_casts_and_defaults(): void
    {
        $user = User::factory()->create();
        $stock = Stock::create(['user_id' => $user->id, 'name' => 'Barang Default', 'quantity' => '12', 'price' => 1500]);
        $stock->refresh();

        $this->assertSame(12, $stock->quantity);
        $this->assertSame(5, $stock->min_stock);
        $this->assertSame('pcs', $stock->unit);
        $this->assertSame('1500.00', $stock->price);
        $this->assertSame('available', $stock->status);
    }

    public function test_stock_factory_states(): void
    {
        $this->assertEquals('available', Stock::factory()->create()->status);
        $this->assertEquals('low_stock', Stock::factory()->lowStock()->create()->status);
        $this->assertEquals('out_of_stock', Stock::factory()->outOfStock()->create()->status);
    }

    public function test_status_enum_uses_stored_or_computed_status(): void
    {
        $stored = Stock::factory()->make(['quantity' => 50, 'min_stock' => 5, 'status' => 'low_stock']);
        $this->assertSame(StockStatus::LOW_STOCK, $stored->statusEnum());

        $invalid = Stock::factory()->make(['quantity' => 0, 'min_stock' => 5, 'status' => 'unknown']);
        $this->assertSame(StockStatus::OUT_OF_STOCK, $invalid->statusEnum());
    }

    public function test_cover_image_url_returns_correct_url_or_null(): void
    {
        $stockWithoutCover = Stock::factory()->create(['cover_image_path' => null]);
        $this->assertNull($stockWithoutCover->coverImageUrl());

        Storage::fake('public');
        Storage::disk('public')->put('stock-covers/sample.png', 'content');

        $stockWithCover = Stock::factory()->create(['cover_image_path' => 'stock-covers/sample.png']);
        $this->assertNotNull($stockWithCover->coverImageUrl());
        $this->assertStringContainsString('stock-covers/sample.png', $stockWithCover->coverImageUrl());
    }
}
