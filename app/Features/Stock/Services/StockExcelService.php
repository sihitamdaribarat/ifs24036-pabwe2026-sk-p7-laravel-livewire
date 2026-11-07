<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockExcelService
{
    /**
     * Spreadsheet header row (column A..J).
     *
     * @var array<int, string>
     */
    public const HEADERS = ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status', 'Created At'];

    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Build a Spreadsheet instance containing stock data.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function buildSpreadsheet(Collection $stocks, string $sheetTitle = 'Stocks'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $columns = range('A', 'J');

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue("{$columns[$index]}1", $header);
        }

        // Header Styling
        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNumber = 2;
        foreach ($stocks as $idx => $stock) {
            $sheet->setCellValue("A{$rowNumber}", $idx + 1);
            $sheet->setCellValue("B{$rowNumber}", $stock->name);
            $sheet->setCellValue("C{$rowNumber}", $stock->sku ?? '-');
            $sheet->setCellValue("D{$rowNumber}", $stock->description ?? '-');
            $sheet->setCellValue("E{$rowNumber}", (int) $stock->quantity);
            $sheet->setCellValue("F{$rowNumber}", $stock->unit);
            $sheet->setCellValue("G{$rowNumber}", $stock->price !== null ? (float) $stock->price : '-');
            $sheet->setCellValue("H{$rowNumber}", (int) $stock->min_stock);
            $sheet->setCellValue("I{$rowNumber}", $stock->status);
            $sheet->setCellValue("J{$rowNumber}", $stock->created_at ? $stock->created_at->format('Y-m-d H:i') : '-');
            $rowNumber++;
        }

        $lastRow = max(2, $rowNumber - 1);
        $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Export stock items as a downloadable streamed response.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function exportToResponse(Collection $stocks, string $filename = 'stocks.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($stocks);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import stock items from an uploaded spreadsheet file path.
     * Expected columns: A No | B Name | C SKU | D Description | E Quantity | F Unit | G Price | H Min Stock | I Status.
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, true);

        $recordsToInsert = [];
        $now = Carbon::now();

        // Skip header row
        array_shift($rows);

        foreach ($rows as $row) {
            $name = $this->cell($row, 'B') ?? $this->cell($row, 'A') ?? '';

            if ($name === '' || in_array(strtolower($name), ['no', 'name'], true)) {
                continue;
            }

            $quantity = $this->toNonNegativeInt($this->cell($row, 'E'), 0);
            $minStock = $this->toNonNegativeInt($this->cell($row, 'H'), 5);
            $statusRaw = strtolower((string) $this->cell($row, 'I'));
            $priceRaw = $this->cell($row, 'G');

            $recordsToInsert[] = [
                'user_id' => $userId,
                'name' => substr($name, 0, 255),
                'sku' => $this->cell($row, 'C'),
                'description' => $this->cell($row, 'D'),
                'quantity' => $quantity,
                'unit' => $this->cell($row, 'F') ?? 'pcs',
                'price' => is_numeric($priceRaw) ? round((float) $priceRaw, 2) : null,
                'min_stock' => $minStock,
                'status' => in_array($statusRaw, StockStatus::values(), true)
                    ? $statusRaw
                    : StockStatus::fromQuantity($quantity, $minStock)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->stockRepository->insertMany($recordsToInsert);

        return [
            'imported' => count($recordsToInsert),
            'errors' => [],
        ];
    }

    /**
     * Read a trimmed cell value; empty strings and "-" are treated as null.
     *
     * @param array<string, mixed> $row
     */
    protected function cell(array $row, string $column): ?string
    {
        $value = trim((string) ($row[$column] ?? ''));

        return ($value === '' || $value === '-') ? null : $value;
    }

    /**
     * Convert a raw cell value to a non-negative integer with a fallback.
     */
    protected function toNonNegativeInt(?string $value, int $default): int
    {
        return is_numeric($value) ? max(0, (int) $value) : $default;
    }
}
