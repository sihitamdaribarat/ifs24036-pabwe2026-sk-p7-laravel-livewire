<?php

namespace Tests\Unit\Services;

use App\Features\Admin\Services\AdminUserService;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminUserService(new StockRepository());
    }

    public function test_get_users_list_without_search_includes_stock_summary(): void
    {
        $user = User::factory()->create();
        User::factory()->count(2)->create();
        Stock::factory()->create(['user_id' => $user->id, 'quantity' => 7]);
        Stock::factory()->create(['user_id' => $user->id, 'quantity' => 3]);

        $list = $this->service->getUsersList();
        $this->assertCount(3, $list);

        $row = $list->firstWhere('id', $user->id);
        $this->assertEquals(2, $row->stocks_count);
        $this->assertEquals(10, (int) $row->stocks_total_quantity);
    }

    public function test_get_users_list_with_search(): void
    {
        User::factory()->create(['name' => 'Alice Wonder', 'email' => 'alice@test.com']);
        User::factory()->create(['name' => 'Bob Marley', 'email' => 'bob@test.com']);
        User::factory()->create(['name' => 'Charlie Chaplin', 'username' => 'charlie_legend', 'email' => 'charlie@test.com']);

        $this->assertCount(1, $this->service->getUsersList(search: 'Alice'));
        $this->assertCount(1, $this->service->getUsersList(search: 'bob@test.com'));
        $this->assertCount(1, $this->service->getUsersList(search: 'charlie_legend'));
    }

    public function test_get_user_stocks(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(4)->create(['user_id' => $user->id]);

        $this->assertCount(4, $this->service->getUserStocks($user->id));
    }
}
