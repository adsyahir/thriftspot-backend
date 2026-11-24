<?php

namespace Database\Factories;

use App\Enums\MalaysianState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $open = ['09:00', '18:00'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'description' => fake()->sentence(12),
            'state' => fake()->randomElement(MalaysianState::cases())->value,
            'location_type' => 'google_maps',
            'address' => fake()->streetAddress(),
            'phone_number' => '+6012'.fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'social_links' => [],
            'operating_hours' => [
                'monday' => $open,
                'tuesday' => $open,
                'wednesday' => $open,
                'thursday' => $open,
                'friday' => $open,
                'saturday' => $open,
                'sunday' => false,
            ],
        ];
    }
}
