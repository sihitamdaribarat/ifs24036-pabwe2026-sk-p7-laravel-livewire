<?php

namespace Tests\Unit\Repositories;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected StockRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StockRepository();
    }

    public function test_create_and_find(): void
    {
        $user = User::factory()->create();
        $stock = $this->repository->create([
            'user_id' => $user->id,
            'name' => 'Test Stock',
            'quantity' => 10,
            'status' => 'available',
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Test Stock']);

        $this->assertEquals($stock->id, $this->repository->find($stock->id)?->id);
        $this->assertNull($this->repository->find(9999));
        $this->assertEquals($stock->id, $this->repository->findOrFail($stock->id)->id);
    }

    public function test_find_or_fail_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->repository->findOrFail(9999);
    }

    public function test_update_and_delete(): void
    {
        $user = User::factory()->create();
        $stock = $this->repository->create(['user_id' => $user->id, 'name' => 'Original']);

        $this->assertTrue($this->repository->update($stock, ['name' => 'Updated Name']));
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Updated Name']);

        $this->assertTrue($this->repository->delete($stock));
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_all_for_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Stock::factory()->count(3)->create(['user_id' => $user->id]);
        Stock::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $this->assertCount(3, $this->repository->allForUser($user->id));
    }

    public function test_for_user_with_filters(): void
    {
        $user = User::factory()->create();

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Laptop Kantor', 'sku' => 'ELK-001', 'description' => 'Inventaris IT', 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Mouse Laptop', 'sku' => 'ELK-002', 'description' => 'Aksesoris', 'status' => 'low_stock']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kursi', 'sku' => 'FUR-001', 'description' => 'Furnitur ruang rapat', 'status' => 'out_of_stock']);

        $this->assertCount(3, $this->repository->forUser($user->id));
        $this->assertCount(1, $this->repository->forUser($user->id, status: 'low_stock'));
        $this->assertCount(2, $this->repository->forUser($user->id, search: 'Laptop'));
        $this->assertCount(1, $this->repository->forUser($user->id, search: 'FUR-'));
        $this->assertCount(1, $this->repository->forUser($user->id, search: 'rapat'));
    }

    public function test_insert_many(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->repository->insertMany([]));

        $records = [
            ['user_id' => $user->id, 'name' => 'Batch 1', 'quantity' => 1, 'status' => 'low_stock', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'name' => 'Batch 2', 'quantity' => 9, 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ];

        $this->assertTrue($this->repository->insertMany($records));
        $this->assertDatabaseCount('stocks', 2);
    }
}
