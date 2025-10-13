<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Oil Change' => ['duration' => 30, 'price' => 2999],
            'Brake Pad Installation' => ['duration' => 120, 'price' => 8999],
            'Chain & Sprocket Replacement' => ['duration' => 90, 'price' => 12999],
        ];
        
        $service = $this->faker->randomElement(array_keys($services));
        $data = $services[$service];
        
        return [
            'shop_id' => \App\Models\Shop::factory(),
            'title' => $service,
            'slug' => \Str::slug($service),
            'duration_minutes' => $data['duration'],
            'price_cents' => $data['price'],
            'active' => true,
        ];
    }
}
