<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationSetting>
 */
class LocationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => \App\Models\Location::factory(),
            'slot_duration_minutes' => 60,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
            'is_weekend_open' => true,
            'weekend_open_time' => '09:00:00',
            'weekend_close_time' => '17:00:00',
            'capacity_per_slot' => $this->faker->numberBetween(2, 6),
        ];
    }
}
