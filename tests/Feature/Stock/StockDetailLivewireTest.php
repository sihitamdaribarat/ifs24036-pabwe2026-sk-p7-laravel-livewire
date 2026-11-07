<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stock_detail(): void
    {
        $stock = Stock::factory()->create();

        $this->get(route('stocks.show', $stock->id))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember);

        $this->get(route('stocks.show', $stock->id))
            ->assertStatus(403);
    }

    public function test_member_can_view_own_stock_detail_page(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('stock-covers/cover.png', 'content');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Printer Laser',
            'description' => '# Spesifikasi',
            'cover_image_path' => 'stock-covers/cover.png',
        ]);

        $this->actingAs($user)
            ->get(route('stocks.show', $stock->id))
            ->assertOk()
            ->assertSee('Printer Laser')
            ->assertSee('stock-covers/cover.png');
    }

    public function test_mount_fills_form_with_auto_or_manual_status(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Barang Utama',
            'sku' => 'BRG-01',
            'description' => '# Deskripsi Markdown',
            'quantity' => 40,
            'unit' => 'box',
            'price' => 12500,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('name', 'Barang Utama')
            ->assertSet('sku', 'BRG-01')
            ->assertSet('description', '# Deskripsi Markdown')
            ->assertSet('quantity', 40)
            ->assertSet('unit', 'box')
            ->assertSet('minStock', 5)
            ->assertSet('status', '')
            ->assertSee('Barang Utama');

        $manual = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 40, 'min_stock' => 5, 'status' => 'low_stock', 'description' => null]);

        Livewire::test(StockDetail::class, ['stock' => $manual])
            ->assertSet('status', 'low_stock')
            ->assertSet('description', '');
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Detail']);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('showEditModal', false)
            ->set('name', 'Sementara')
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Nama Detail')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Awal', 'quantity' => 50, 'min_stock' => 5]);

        $this->actingAs($user);

        // Validation errors
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'ab') // min:3
            ->set('quantity', -5)
            ->set('status', 'invalid_status')
            ->call('saveStock')
            ->assertHasErrors(['name', 'quantity', 'status']);

        // Success with automatic status
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Telah Diperbarui')
            ->set('sku', '')
            ->set('description', '## Rincian Baru')
            ->set('quantity', 0)
            ->set('unit', 'kg')
            ->set('price', '')
            ->set('minStock', 2)
            ->set('status', '')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diperbarui',
            'sku' => null,
            'description' => '## Rincian Baru',
            'quantity' => 0,
            'unit' => 'kg',
            'price' => null,
            'min_stock' => 2,
            'status' => 'out_of_stock',
        ]);

        // Success with manual status
        Livewire::test(StockDetail::class, ['stock' => $stock->fresh()])
            ->set('quantity', 100)
            ->set('price', '15000')
            ->set('status', 'low_stock')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'quantity' => 100,
            'status' => 'low_stock',
        ]);
    }

    public function test_adjust_quantity_increment_and_decrement(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 10, 'min_stock' => 5, 'status' => 'available']);

        $this->actingAs($user);

        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('adjustAmount', 6)
            ->call('adjustQuantity', -1)
            ->assertHasNoErrors()
            ->assertSet('quantity', 4);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 4, 'status' => 'low_stock']);

        $component->set('adjustAmount', 20)
            ->call('adjustQuantity', 1)
            ->assertSet('quantity', 24);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 24, 'status' => 'available']);

        $component->set('adjustAmount', 100)
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', 0);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 0, 'status' => 'out_of_stock']);

        // Invalid amount
        $component->set('adjustAmount', 0)
            ->call('adjustQuantity', 1)
            ->assertHasErrors(['adjustAmount']);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user);

        // Invalid file
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $invalidFile)
            ->assertHasErrors(['coverPhoto']);

        // Valid image upload
        $imageFile = UploadedFile::fake()->image('cover.jpg', 800, 400);
        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $imageFile)
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);

        // Delete cover photo
        $component->call('deleteCoverPhoto')
            ->assertSet('confirmingCoverDeletion', false);

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
    }

    public function test_delete_stock_redirects(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_modal_actions(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false);
    }
}
