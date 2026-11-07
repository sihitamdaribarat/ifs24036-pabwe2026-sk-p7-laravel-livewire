<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService(new StockRepository());
    }

    public function test_get_stocks_for_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $this->service->getStocksForUser($user->id));
    }

    public function test_create_stock_with_full_data(): void
    {
        $user = User::factory()->create();
        $stock = $this->service->createStock($user->id, [
            'name' => 'Service Stock',
            'sku' => 'SRV-1',
            'description' => 'Desc',
            'quantity' => 20,
            'unit' => 'box',
            'price' => 12000,
            'min_stock' => 4,
            'status' => 'low_stock',
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals('Service Stock', $stock->name);
        $this->assertEquals('box', $stock->unit);
        $this->assertEquals('low_stock', $stock->status); // explicit status kept
    }

    public function test_create_stock_with_defaults_derives_status(): void
    {
        $user = User::factory()->create();

        $out = $this->service->createStock($user->id, ['name' => 'Minimal']);
        $this->assertEquals(0, $out->quantity);
        $this->assertEquals(5, $out->min_stock);
        $this->assertEquals('pcs', $out->unit);
        $this->assertNull($out->sku);
        $this->assertEquals('out_of_stock', $out->status);

        $available = $this->service->createStock($user->id, ['name' => 'Banyak', 'quantity' => 9, 'unit' => '', 'status' => '']);
        $this->assertEquals('pcs', $available->unit);
        $this->assertEquals('available', $available->status);

        $negative = $this->service->createStock($user->id, ['name' => 'Negatif', 'quantity' => -3, 'min_stock' => -1]);
        $this->assertEquals(0, $negative->quantity);
        $this->assertEquals(0, $negative->min_stock);
    }

    public function test_update_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Old Name', 'quantity' => 50, 'min_stock' => 5, 'unit' => 'pcs', 'status' => 'available']);

        // Partial update keeps other fields
        $this->assertTrue($this->service->updateStock($stock, ['name' => 'New Name', 'description' => 'Updated Desc']));
        $stock->refresh();
        $this->assertEquals('New Name', $stock->name);
        $this->assertEquals('Updated Desc', $stock->description);
        $this->assertEquals(50, $stock->quantity);
        $this->assertEquals('pcs', $stock->unit);
        $this->assertEquals('available', $stock->status);

        // Empty status => recomputed from new quantity
        $this->service->updateStock($stock, ['quantity' => 2, 'status' => '', 'unit' => 'kg', 'sku' => null, 'price' => null]);
        $stock->refresh();
        $this->assertEquals(2, $stock->quantity);
        $this->assertEquals('kg', $stock->unit);
        $this->assertEquals('low_stock', $stock->status);
        $this->assertNull($stock->sku);
        $this->assertNull($stock->price);

        // Explicit status is respected
        $this->service->updateStock($stock, ['min_stock' => 1, 'status' => 'out_of_stock']);
        $stock->refresh();
        $this->assertEquals(1, $stock->min_stock);
        $this->assertEquals('out_of_stock', $stock->status);
    }

    public function test_adjust_quantity_syncs_status_and_never_goes_negative(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 6, 'min_stock' => 5, 'status' => 'available']);

        $this->assertTrue($this->service->adjustQuantity($stock, -1));
        $stock->refresh();
        $this->assertEquals(5, $stock->quantity);
        $this->assertEquals('low_stock', $stock->status);

        $this->service->adjustQuantity($stock, -50);
        $stock->refresh();
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals('out_of_stock', $stock->status);

        $this->service->adjustQuantity($stock, 10);
        $stock->refresh();
        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals('available', $stock->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->service->deleteStock($stock));
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_cover_photo_management(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        // Update cover
        $path = $this->service->updateCoverPhoto($stock, UploadedFile::fake()->image('cover.jpg'));
        $stock->refresh();
        $this->assertEquals($path, $stock->cover_image_path);
        Storage::disk('public')->assertExists($path);

        // Update cover again (replaces old)
        $newPath = $this->service->updateCoverPhoto($stock, UploadedFile::fake()->image('new-cover.jpg'));
        $stock->refresh();
        $this->assertEquals($newPath, $stock->cover_image_path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);

        // Delete cover
        $this->assertTrue($this->service->deleteCoverPhoto($stock));
        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
        Storage::disk('public')->assertMissing($newPath);

        // Delete stock cleans up cover photo if present
        $anotherPath = $this->service->updateCoverPhoto($stock, UploadedFile::fake()->image('another.jpg'));
        Storage::disk('public')->assertExists($anotherPath);

        $this->service->deleteStock($stock);
        Storage::disk('public')->assertMissing($anotherPath);
    }
}
