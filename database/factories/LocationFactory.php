<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => \App\Models\Shop::factory(),
            'shopify_location_gid' => 'gid://shopify/Location/' . $this->faker->randomNumber(9),
            'name' => $this->faker->company() . ' Service Center',
            'timezone' => $this->faker->randomElement(['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Toronto']),
            'is_active' => true,
        ];
    }
}
