<?php

namespace Database\Seeders;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'username' => 'member',
                'password' => Hash::make('member'),
                'role' => 'member',
            ]
        );

        $items = [
            ['name' => 'Kertas HVS A4 80gsm', 'sku' => 'ATK-HVS-A4', 'quantity' => 120, 'unit' => 'rim', 'price' => 55000, 'min_stock' => 20,
                'description' => "## Kertas HVS A4\n\n- Gramatur **80gsm**\n- Isi 500 lembar per rim"],
            ['name' => 'Tinta Printer Hitam', 'sku' => 'ATK-INK-BLK', 'quantity' => 4, 'unit' => 'botol', 'price' => 85000, 'min_stock' => 5,
                'description' => 'Tinta refill untuk printer kantor. Segera lakukan **restock**.'],
            ['name' => 'Mouse Wireless', 'sku' => 'ELK-MOUSE-01', 'quantity' => 0, 'unit' => 'pcs', 'price' => 125000, 'min_stock' => 3,
                'description' => 'Mouse wireless 2.4GHz dengan receiver USB.'],
            ['name' => 'Kabel LAN Cat6', 'sku' => 'NET-CAT6', 'quantity' => 35, 'unit' => 'meter', 'price' => 7500, 'min_stock' => 10,
                'description' => 'Kabel jaringan UTP Cat6 untuk instalasi ruangan.'],
        ];

        foreach ($items as $item) {
            Stock::firstOrCreate(
                ['user_id' => $member->id, 'sku' => $item['sku']],
                $item + ['status' => StockStatus::fromQuantity($item['quantity'], $item['min_stock'])->value]
            );
        }
    }
}
