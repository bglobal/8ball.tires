<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resourceTypes = [
            'Bay 1' => 2,
            'Bay 2' => 2,
            'Bay 3' => 1,
            'Technician Station A' => 1,
            'Technician Station B' => 1,
            'Express Service Bay' => 1,
        ];
        
        $resource = $this->faker->randomElement(array_keys($resourceTypes));
        
        return [
            'location_id' => \App\Models\Location::factory(),
            'name' => $resource,
            'seats' => $resourceTypes[$resource],
        ];
    }
}
