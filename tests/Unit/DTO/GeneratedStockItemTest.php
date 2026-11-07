<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Kertas HVS A4',
            sku: 'ATK-HVS-A4',
            description: 'Kertas 80gsm',
            quantity: 3,
            unit: 'rim',
            price: 55000.0,
            minStock: 5,
        );

        $this->assertEquals('Kertas HVS A4', $dto->name);
        $this->assertEquals('ATK-HVS-A4', $dto->sku);
        $this->assertEquals('Kertas 80gsm', $dto->description);
        $this->assertEquals(3, $dto->quantity);
        $this->assertEquals('rim', $dto->unit);
        $this->assertEquals(55000.0, $dto->price);
        $this->assertEquals(5, $dto->minStock);
        $this->assertEquals('low_stock', $dto->status());

        $this->assertEquals([
            'name' => 'Kertas HVS A4',
            'sku' => 'ATK-HVS-A4',
            'description' => 'Kertas 80gsm',
            'quantity' => 3,
            'unit' => 'rim',
            'price' => 55000.0,
            'min_stock' => 5,
            'status' => 'low_stock',
        ], $dto->toArray());
    }

    public function test_dto_defaults(): void
    {
        $dto = new GeneratedStockItem('Barang Baru');

        $this->assertNull($dto->sku);
        $this->assertNull($dto->description);
        $this->assertEquals(0, $dto->quantity);
        $this->assertEquals('pcs', $dto->unit);
        $this->assertNull($dto->price);
        $this->assertEquals(5, $dto->minStock);
        $this->assertEquals('out_of_stock', $dto->status());
    }
}
