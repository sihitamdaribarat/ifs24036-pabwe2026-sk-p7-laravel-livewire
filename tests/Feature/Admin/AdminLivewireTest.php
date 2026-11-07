<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_forbidden_from_admin_pages(): void
    {
        $member = User::factory()->member()->create();
        $targetUser = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/admin/users')
            ->assertStatus(403);

        $this->actingAs($member)
            ->get("/admin/users/{$targetUser->id}/stocks")
            ->assertStatus(403);
    }

    public function test_admin_can_open_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Gudang Utama']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Palet Kayu']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Gudang Utama');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/stocks")
            ->assertOk()
            ->assertSee('Palet Kayu')
            ->assertSee('Read-Only');
    }

    public function test_admin_can_view_user_list_with_stock_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@test.com']);
        User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@test.com']);
        Stock::factory()->create(['user_id' => $budi->id, 'quantity' => 1200]);
        Stock::factory()->create(['user_id' => $budi->id, 'quantity' => 34]);

        $this->actingAs($admin);

        Livewire::test(UserList::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Lestari')
            ->assertSee('1.234')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Lestari');
    }

    public function test_admin_can_view_user_stocks_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Dewi Sartika']);

        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Semen Portland', 'quantity' => 50, 'min_stock' => 5]);
        Stock::factory()->outOfStock()->create(['user_id' => $member->id, 'name' => 'Pasir Silika']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Dewi Sartika')
            ->assertSee('Semen Portland')
            ->assertSee('Pasir Silika')
            ->set('search', 'Semen')
            ->assertSee('Semen Portland')
            ->assertDontSee('Pasir Silika')
            ->set('search', '')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Pasir Silika')
            ->assertDontSee('Semen Portland');
    }

    public function test_admin_can_view_but_not_modify_member_stock_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang Member', 'quantity' => 10]);

        $this->actingAs($admin);

        $this->get(route('stocks.show', $stock->id))->assertOk();

        foreach ([['adjustQuantity', 1], ['openEditModal'], ['saveStock'], ['deleteCoverPhoto'], ['deleteStock']] as $call) {
            Livewire::test(StockDetail::class, ['stock' => $stock])
                ->call(...$call)
                ->assertForbidden();
        }

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Barang Member', 'quantity' => 10]);
    }

    public function test_admin_can_export_user_stocks(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Eko Prasetyo']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang Export Admin']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
