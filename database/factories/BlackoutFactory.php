<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blackout>
 */
class BlackoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = [
            'Holiday - New Year\'s Day',
            'Holiday - Independence Day',
            'Holiday - Christmas Day',
            'Maintenance - Equipment Service',
            'Staff Training Day',
            'Emergency Closure',
            'Weather - Severe Storm',
        ];
        
        return [
            'location_id' => \App\Models\Location::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+90 days'),
            'reason' => $this->faker->randomElement($reasons),
        ];
    }
}
