<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class StockService
{
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Get paginated stock items for the specified user.
     */
    public function getStocksForUser(int $userId, ?string $status = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->stockRepository->forUser($userId, $status, $search, $perPage);
    }

    /**
     * Create a new stock item for the user.
     * When no status is given, it is derived from quantity vs min_stock.
     *
     * @param array<string, mixed> $data
     */
    public function createStock(int $userId, array $data): Stock
    {
        $quantity = max(0, (int) ($data['quantity'] ?? 0));
        $minStock = max(0, (int) ($data['min_stock'] ?? 5));

        return $this->stockRepository->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'quantity' => $quantity,
            'unit' => ($data['unit'] ?? null) ?: 'pcs',
            'price' => $data['price'] ?? null,
            'min_stock' => $minStock,
            'status' => ($data['status'] ?? null) ?: StockStatus::fromQuantity($quantity, $minStock)->value,
            'cover_image_path' => $data['cover_image_path'] ?? null,
        ]);
    }

    /**
     * Update an existing stock item.
     * When the given status is empty, it is re-derived from quantity vs min_stock.
     *
     * @param array<string, mixed> $data
     */
    public function updateStock(Stock $stock, array $data): bool
    {
        $quantity = array_key_exists('quantity', $data) ? max(0, (int) $data['quantity']) : $stock->quantity;
        $minStock = array_key_exists('min_stock', $data) ? max(0, (int) $data['min_stock']) : $stock->min_stock;

        $status = array_key_exists('status', $data)
            ? ($data['status'] ?: StockStatus::fromQuantity($quantity, $minStock)->value)
            : $stock->status;

        return $this->stockRepository->update($stock, [
            'name' => $data['name'] ?? $stock->name,
            'sku' => array_key_exists('sku', $data) ? $data['sku'] : $stock->sku,
            'description' => array_key_exists('description', $data) ? $data['description'] : $stock->description,
            'quantity' => $quantity,
            'unit' => ($data['unit'] ?? null) ?: $stock->unit,
            'price' => array_key_exists('price', $data) ? $data['price'] : $stock->price,
            'min_stock' => $minStock,
            'status' => $status,
            'cover_image_path' => array_key_exists('cover_image_path', $data) ? $data['cover_image_path'] : $stock->cover_image_path,
        ]);
    }

    /**
     * Increase (positive delta) or decrease (negative delta) the quantity.
     * Quantity never drops below zero and the status always follows the new quantity.
     */
    public function adjustQuantity(Stock $stock, int $delta): bool
    {
        $newQuantity = max(0, (int) $stock->quantity + $delta);

        return $this->stockRepository->update($stock, [
            'quantity' => $newQuantity,
            'status' => StockStatus::fromQuantity($newQuantity, (int) $stock->min_stock)->value,
        ]);
    }

    /**
     * Update or upload a cover image for the stock item.
     */
    public function updateCoverPhoto(Stock $stock, mixed $file): string
    {
        $this->removeStoredCover($stock);

        $path = $file->store('stock-covers', 'public');
        $this->stockRepository->update($stock, ['cover_image_path' => $path]);

        return $path;
    }

    /**
     * Delete the cover image of the stock item.
     */
    public function deleteCoverPhoto(Stock $stock): bool
    {
        $this->removeStoredCover($stock);

        return $this->stockRepository->update($stock, ['cover_image_path' => null]);
    }

    /**
     * Delete a stock item and its cover image if exists.
     */
    public function deleteStock(Stock $stock): bool
    {
        $this->removeStoredCover($stock);

        return $this->stockRepository->delete($stock);
    }

    /**
     * Remove the stored cover file from the public disk when present.
     */
    protected function removeStoredCover(Stock $stock): void
    {
        if ($stock->cover_image_path && Storage::disk('public')->exists($stock->cover_image_path)) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }
    }
}
