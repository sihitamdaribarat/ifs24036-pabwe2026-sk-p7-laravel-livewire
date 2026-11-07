<?php

namespace Tests\Unit\Enums;

use App\Features\Stock\Enums\StockStatus;
use PHPUnit\Framework\TestCase;

class StockStatusTest extends TestCase
{
    public function test_stock_status_labels(): void
    {
        $this->assertEquals('Tersedia', StockStatus::AVAILABLE->label());
        $this->assertEquals('Stok Menipis', StockStatus::LOW_STOCK->label());
        $this->assertEquals('Habis', StockStatus::OUT_OF_STOCK->label());
    }

    public function test_stock_status_badge_classes_green_yellow_red(): void
    {
        $this->assertStringContainsString('emerald', StockStatus::AVAILABLE->badgeClass());
        $this->assertStringContainsString('yellow', StockStatus::LOW_STOCK->badgeClass());
        $this->assertStringContainsString('rose', StockStatus::OUT_OF_STOCK->badgeClass());
    }

    public function test_status_from_quantity(): void
    {
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::fromQuantity(0, 5));
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::fromQuantity(-3, 5));
        $this->assertSame(StockStatus::LOW_STOCK, StockStatus::fromQuantity(5, 5));
        $this->assertSame(StockStatus::LOW_STOCK, StockStatus::fromQuantity(1, 5));
        $this->assertSame(StockStatus::AVAILABLE, StockStatus::fromQuantity(6, 5));
        $this->assertSame(StockStatus::AVAILABLE, StockStatus::fromQuantity(1, 0));
    }

    public function test_values(): void
    {
        $this->assertSame(['available', 'low_stock', 'out_of_stock'], StockStatus::values());
    }
}
