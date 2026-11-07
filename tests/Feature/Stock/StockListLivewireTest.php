<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stocks(): void
    {
        $this->get('/stocks')->assertRedirect(route('login'));
    }

    public function test_member_can_open_stock_page(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Lakban Coklat']);

        $this->actingAs($user)
            ->get('/stocks')
            ->assertOk()
            ->assertSee('Daftar Stok Barang Saya')
            ->assertSee('Lakban Coklat');
    }

    public function test_member_can_view_only_own_stocks(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Stok Milik Member']);

        $otherUser = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $otherUser->id, 'name' => 'Stok Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Stok Milik Member')
            ->assertDontSee('Stok Rahasia Orang Lain');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validation error
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('formName', '')
            ->set('formQuantity', -1)
            ->set('formMinStock', 'abc')
            ->set('formPrice', 'mahal')
            ->set('formStatus', 'invalid_status')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formMinStock', 'formPrice', 'formStatus']);

        // Success with automatic status (quantity <= min_stock => low_stock)
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Kertas HVS')
            ->set('formSku', 'ATK-001')
            ->set('formDescription', '**Kertas** A4')
            ->set('formQuantity', 3)
            ->set('formUnit', 'rim')
            ->set('formPrice', '55000')
            ->set('formMinStock', 5)
            ->set('formStatus', '')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Kertas HVS',
            'sku' => 'ATK-001',
            'quantity' => 3,
            'unit' => 'rim',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);

        // Empty price & SKU stored as null
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Barang Tanpa Harga')
            ->set('formSku', '')
            ->set('formPrice', '')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'name' => 'Barang Tanpa Harga',
            'sku' => null,
            'price' => null,
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_edit_stock_success_with_auto_and_manual_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Status equals the computed one -> form shows "otomatis" (empty)
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Lama', 'quantity' => 50, 'min_stock' => 5, 'status' => 'available']);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formStatus', '')
            ->set('formName', 'Nama Telah Diedit')
            ->set('formQuantity', 2)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diedit',
            'quantity' => 2,
            'status' => 'low_stock',
        ]);

        // Manually overridden status is kept in the form
        $manual = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 50, 'min_stock' => 5, 'status' => 'low_stock']);

        Livewire::test(StockList::class)
            ->call('openEditModal', $manual->id)
            ->assertSet('formStatus', 'low_stock');
    }

    public function test_member_cannot_edit_other_member_stock(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, 5)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id)
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => $stock->quantity]);
    }

    public function test_adjust_quantity_updates_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 6, 'min_stock' => 5, 'status' => 'available']);

        $component = Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, -1);
        $this->assertEquals(5, $stock->fresh()->quantity);
        $this->assertEquals('low_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -10);
        $this->assertEquals(0, $stock->fresh()->quantity);
        $this->assertEquals('out_of_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, 1);
        $this->assertEquals(1, $stock->fresh()->quantity);
        $this->assertEquals('low_stock', $stock->fresh()->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('deleteStock')
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Pulpen Biru', 'sku' => 'PEN-BLUE', 'quantity' => 0, 'status' => 'out_of_stock']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Pensil 2B', 'sku' => 'PCL-2B', 'quantity' => 100, 'status' => 'available']);

        Livewire::test(StockList::class)
            ->set('search', 'Pulpen')
            ->assertSee('Pulpen Biru')
            ->assertDontSee('Pensil 2B')
            ->set('search', 'PCL-2B')
            ->assertSee('Pensil 2B')
            ->assertDontSee('Pulpen Biru')
            ->set('search', '')
            ->set('statusFilter', 'available')
            ->assertSee('Pensil 2B')
            ->assertDontSee('Pulpen Biru');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'],
            [1, 'Imported Excel Stock', 'IMP-001', 'Excel Desc', 25, 'box', 15000, 5, ''],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadedFile = UploadedFile::fake()->createWithContent('stocks.xlsx', $fileContent);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $uploadedFile)
            ->call('importExcel')
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'sku' => 'IMP-001',
            'quantity' => 25,
            'unit' => 'box',
            'status' => 'available',
        ]);
    }

    public function test_import_excel_requires_file(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->call('importExcel')
            ->assertHasErrors(['importFile']);
    }

    public function test_ai_generation_preview_select_and_save(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $aiClient = $this->createStub(AiClientInterface::class);
        $aiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem('Kabel HDMI 2m', 'ELK-HDMI-2M', 'Kabel HDMI 4K', 20, 'pcs', 45000, 5),
            new GeneratedStockItem('Toner Printer', null, null, 2, 'pcs', null, 3),
        ]);
        $this->app->instance(AiClientInterface::class, $aiClient);

        // Validation for prompt and count
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Stok peralatan elektronik kantor')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2)
            ->assertSee('Kabel HDMI 2m')
            ->assertSee('Toner Printer');

        // Saving with no items selected
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        // Save only the first item (index 5 does not exist and is ignored)
        $component->set('aiSelectedIndices', [0, 5])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Kabel HDMI 2m',
            'sku' => 'ELK-HDMI-2M',
            'quantity' => 20,
            'status' => 'available',
        ]);
        $this->assertDatabaseMissing('stocks', ['name' => 'Toner Printer']);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $aiClient = $this->createStub(AiClientInterface::class);
        $aiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $aiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded')
            ->assertSet('aiIsGenerating', false);
    }

    public function test_close_modals(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->call('closeFormModal')
            ->assertSet('showFormModal', false)
            ->call('openImportModal')
            ->call('closeImportModal')
            ->assertSet('showImportModal', false)
            ->call('openAiModal')
            ->call('closeAiModal')
            ->assertSet('showAiModal', false);
    }
}
