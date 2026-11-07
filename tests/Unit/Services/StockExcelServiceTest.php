<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockExcelService(new StockRepository());
    }

    public function test_build_spreadsheet_and_export_response(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang A', 'sku' => 'A-1', 'quantity' => 7, 'price' => 2500]);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang B', 'sku' => null, 'description' => null, 'price' => null]);
        $stocks = $user->stocks()->orderBy('id')->get();

        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertEquals('Custom Stocks', $sheet->getTitle());
        $this->assertEquals(StockExcelService::HEADERS, $sheet->rangeToArray('A1:J1')[0]);
        $this->assertEquals('Barang A', $sheet->getCell('B2')->getValue());
        $this->assertEquals(7, $sheet->getCell('E2')->getValue());
        $this->assertEquals(2500, $sheet->getCell('G2')->getValue());
        $this->assertEquals('-', $sheet->getCell('C3')->getValue());
        $this->assertEquals('-', $sheet->getCell('G3')->getValue());

        $response = $this->service->exportToResponse($stocks, 'test.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $this->assertNotEmpty(ob_get_clean());
    }

    public function test_exported_file_can_be_imported_back(): void
    {
        $owner = User::factory()->create();
        Stock::factory()->create(['user_id' => $owner->id, 'name' => 'Roundtrip Item', 'sku' => 'RT-1', 'quantity' => 4, 'min_stock' => 5, 'price' => 1234.5, 'unit' => 'box']);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_roundtrip_').'.xlsx';
        (new Xlsx($this->service->buildSpreadsheet($owner->stocks()->get())))->save($tempPath);

        $receiver = User::factory()->create();
        $result = $this->service->import($tempPath, $receiver->id);
        @unlink($tempPath);

        $this->assertEquals(1, $result['imported']);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $receiver->id,
            'name' => 'Roundtrip Item',
            'sku' => 'RT-1',
            'quantity' => 4,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
        $this->assertEquals('1234.50', Stock::where('user_id', $receiver->id)->first()->price);
    }

    public function test_import_spreadsheet_variants(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'],
            // Full row with explicit valid status
            [1, 'Import Lengkap', 'IMP-1', 'Desc 1', 30, 'kg', '7500', 10, 'available'],
            // Invalid status, negative qty, '-' placeholders, non numeric price/min stock
            [2, 'Import Kosong', '-', '-', -5, '', 'abc', 'x', 'unknown_status'],
            // Column B empty: fallback to Column A as name, low stock computed
            ['Barang dari Kolom A', '', null, 'Col A Desc', 2, 'pcs', null, 5, 'LOW_STOCK'],
            // Duplicate header row and empty row are ignored
            ['No', 'Name', 'SKU'],
            [null, null, null],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(3, $result['imported']);
        $this->assertSame([], $result['errors']);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Import Lengkap',
            'sku' => 'IMP-1',
            'quantity' => 30,
            'unit' => 'kg',
            'min_stock' => 10,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Import Kosong',
            'sku' => null,
            'description' => null,
            'quantity' => 0,
            'unit' => 'pcs',
            'price' => null,
            'min_stock' => 5,
            'status' => 'out_of_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang dari Kolom A',
            'quantity' => 2,
            'status' => 'low_stock',
        ]);
    }

    public function test_import_csv_with_only_header(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([StockExcelService::HEADERS]);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_csv_').'.csv';
        IOFactory::createWriter($spreadsheet, 'Csv')->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(0, $result['imported']);
        $this->assertDatabaseCount('stocks', 0);
    }
}
