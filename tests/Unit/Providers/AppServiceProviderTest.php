<?php

namespace Tests\Unit\Providers;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Repositories\StockRepository;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_service_bindings(): void
    {
        $this->assertInstanceOf(StockRepository::class, app(StockRepositoryInterface::class));
        $this->assertInstanceOf(OpenAiStockGeneratorService::class, app(AiClientInterface::class));
    }

    public function test_force_https_configuration(): void
    {
        config(['app.force_https' => true]);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertStringStartsWith('https://', URL::to('/test'));

        // Reset
        config(['app.force_https' => false]);
        URL::forceScheme(null);
    }

    public function test_rate_limiters(): void
    {
        $request = Request::create('/test', 'GET');

        foreach (['1000-per-second', 'global', 'api'] as $name) {
            $limiter = RateLimiter::limiter($name);
            $this->assertNotNull($limiter);
            $this->assertEquals(1000, $limiter($request)->maxAttempts);
        }
    }
}
