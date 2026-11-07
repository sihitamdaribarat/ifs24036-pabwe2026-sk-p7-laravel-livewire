<?php

namespace App\Features\Stock\Enums;

enum StockStatus: string
{
    case AVAILABLE = 'available';
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::LOW_STOCK => 'Stok Menipis',
            self::OUT_OF_STOCK => 'Habis',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AVAILABLE => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::LOW_STOCK => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            self::OUT_OF_STOCK => 'bg-rose-100 text-rose-800 border-rose-300',
        };
    }

    /**
     * Determine the status based on the current quantity and the minimum stock threshold.
     */
    public static function fromQuantity(int $quantity, int $minStock): self
    {
        if ($quantity <= 0) {
            return self::OUT_OF_STOCK;
        }

        return $quantity <= $minStock ? self::LOW_STOCK : self::AVAILABLE;
    }

    /**
     * Get all raw status values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
