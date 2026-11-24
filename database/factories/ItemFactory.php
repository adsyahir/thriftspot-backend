<?php

namespace Database\Factories;

use App\Enums\ItemCategory;
use App\Enums\ItemCondition;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(10),
            'category' => fake()->randomElement(ItemCategory::cases())->value,
            'condition' => fake()->randomElement(ItemCondition::cases())->value,
            'status' => 'active',
            'price' => fake()->randomFloat(2, 5, 500),
            'image_path' => null,
        ];
    }
}
