<?php

namespace App\Features\Admin\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserService
{
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Get paginated list of all users with their stock summary
     * (number of stock items and total quantity).
     */
    public function getUsersList(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = User::query()
            ->withCount('stocks')
            ->withSum('stocks as stocks_total_quantity', 'quantity');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Get read-only list of stock items for a specific target user.
     */
    public function getUserStocks(int $userId, ?string $status = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->stockRepository->forUser($userId, $status, $search, $perPage);
    }
}
