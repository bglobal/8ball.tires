<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServicePart>
 */
class ServicePartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parts = [
            'Motor Oil 5W-30' => 1,
            'Oil Filter' => 1,
            'Brake Pads (Front)' => 1,
            'Brake Pads (Rear)' => 1,
            'Brake Fluid' => 1,
            'Chain' => 1,
            'Front Sprocket' => 1,
            'Rear Sprocket' => 1,
            'Chain Lube' => 1,
        ];
        
        $part = $this->faker->randomElement(array_keys($parts));
        
        return [
            'service_id' => \App\Models\Service::factory(),
            'shopify_variant_gid' => 'gid://shopify/ProductVariant/' . $this->faker->randomNumber(9),
            'qty_per_service' => $parts[$part],
        ];
    }
}
