<?php

namespace Database\Factories;

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
        return [
            'shopify_domain' => $this->faker->unique()->domainName(),
            'admin_api_token' => 'shpat_' . $this->faker->sha256(),
            'currency' => $this->faker->randomElement(['USD', 'CAD', 'EUR', 'GBP']),
        ];
    }
}
