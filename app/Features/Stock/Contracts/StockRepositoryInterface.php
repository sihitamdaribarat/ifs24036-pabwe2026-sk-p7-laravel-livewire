<?php

namespace App\Features\Stock\Contracts;

use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface StockRepositoryInterface
{
    /**
     * Get paginated stock items for a given user with optional status and search filters.
     */
    public function forUser(int $userId, ?string $status = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator;

    /**
     * Get all stock items for a given user without pagination.
     *
     * @return Collection<int, Stock>
     */
    public function allForUser(int $userId): Collection;

    /**
     * Find a stock item by id.
     */
    public function find(int $id): ?Stock;

    /**
     * Find a stock item by id or throw an exception.
     */
    public function findOrFail(int $id): Stock;

    /**
     * Create a new stock item.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Stock;

    /**
     * Update an existing stock item.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(Stock $stock, array $attributes): bool;

    /**
     * Delete an existing stock item.
     */
    public function delete(Stock $stock): bool;

    /**
     * Insert multiple stock items at once.
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function insertMany(array $records): bool;
}
