<?php

namespace Database\Factories;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucwords(fake()->words(3, true)),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('???-####')),
            'description' => fake()->paragraph(),
            'quantity' => 50,
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter']),
            'price' => fake()->randomFloat(2, 1000, 500000),
            'min_stock' => 5,
            'status' => fn (array $attributes) => StockStatus::fromQuantity(
                (int) $attributes['quantity'],
                (int) $attributes['min_stock']
            )->value,
        ];
    }

    /**
     * Indicate that the stock is running low.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 3,
            'min_stock' => 5,
            'status' => StockStatus::LOW_STOCK->value,
        ]);
    }

    /**
     * Indicate that the stock is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'status' => StockStatus::OUT_OF_STOCK->value,
        ]);
    }
}
