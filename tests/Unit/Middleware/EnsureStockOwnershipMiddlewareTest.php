<?php

namespace Tests\Unit\Middleware;

use App\Features\Stock\Models\Stock;
use App\Http\Middleware\EnsureStockOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureStockOwnershipMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a request bound to a route that carries the given parameter.
     */
    private function makeRequest(User $actor, string $parameter, mixed $value, string $method = 'GET'): Request
    {
        $request = Request::create('/test', $method);
        $request->setUserResolver(fn () => $actor);

        $route = new Route($method, '/test/{'.$parameter.'}', []);
        $route->bind($request);
        $route->setParameter($parameter, $value);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function assertForbidden(EnsureStockOwnership $middleware, Request $request): void
    {
        try {
            $middleware->handle($request, fn () => new Response('OK'));
            $this->fail('Expected HttpException 403');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_blocks_unauthenticated_request(): void
    {
        $middleware = new EnsureStockOwnership();
        $request = Request::create('/stocks', 'GET');

        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('OK'));
    }

    public function test_request_without_route_parameters_passes(): void
    {
        $middleware = new EnsureStockOwnership();
        $member = User::factory()->member()->create();

        $request = Request::create('/stocks', 'GET');
        $request->setUserResolver(fn () => $member);

        $this->assertEquals(200, $middleware->handle($request, fn () => new Response('OK'))->getStatusCode());
    }

    public function test_route_user_parameter_ownership(): void
    {
        $middleware = new EnsureStockOwnership();
        $member1 = User::factory()->member()->create();
        $member2 = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        // Member accessing own (model and raw id)
        $this->assertEquals(200, $middleware->handle($this->makeRequest($member1, 'user', $member1), fn () => new Response('OK'))->getStatusCode());
        $this->assertEquals(200, $middleware->handle($this->makeRequest($member1, 'user', (string) $member1->id), fn () => new Response('OK'))->getStatusCode());

        // Member accessing other user -> 403
        $this->assertForbidden($middleware, $this->makeRequest($member1, 'user', $member2));

        // Admin accessing other user -> allowed
        $this->assertEquals(200, $middleware->handle($this->makeRequest($admin, 'user', $member2), fn () => new Response('OK'))->getStatusCode());
    }

    public function test_route_stock_parameter_ownership(): void
    {
        $middleware = new EnsureStockOwnership();
        $member1 = User::factory()->member()->create();
        $member2 = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        $stock1 = Stock::factory()->create(['user_id' => $member1->id]);

        // Owner accessing own stock (model and raw id)
        $this->assertEquals(200, $middleware->handle($this->makeRequest($member1, 'stock', $stock1), fn () => new Response('OK'))->getStatusCode());
        $this->assertEquals(200, $middleware->handle($this->makeRequest($member1, 'stock', (string) $stock1->id), fn () => new Response('OK'))->getStatusCode());

        // Unknown stock id passes through (route model binding will 404 later)
        $this->assertEquals(200, $middleware->handle($this->makeRequest($member1, 'stock', '999999'), fn () => new Response('OK'))->getStatusCode());

        // Other member -> 403
        $this->assertForbidden($middleware, $this->makeRequest($member2, 'stock', $stock1));

        // Admin GET -> allowed (read-only)
        $this->assertEquals(200, $middleware->handle($this->makeRequest($admin, 'stock', $stock1), fn () => new Response('OK'))->getStatusCode());

        // Admin attempting to modify (DELETE) -> 403
        $this->assertForbidden($middleware, $this->makeRequest($admin, 'stock', $stock1, 'DELETE'));
    }
}
