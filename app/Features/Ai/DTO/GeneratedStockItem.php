<?php

namespace App\Features\Ai\DTO;

use App\Features\Stock\Enums\StockStatus;

class GeneratedStockItem
{
    public function __construct(
        public string $name,
        public ?string $sku = null,
        public ?string $description = null,
        public int $quantity = 0,
        public string $unit = 'pcs',
        public ?float $price = null,
        public int $minStock = 5,
    ) {}

    /**
     * Status derived from quantity vs min_stock.
     */
    public function status(): string
    {
        return StockStatus::fromQuantity($this->quantity, $this->minStock)->value;
    }

    /**
     * Convert the DTO to an associative array for persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'min_stock' => $this->minStock,
            'status' => $this->status(),
        ];
    }
}
