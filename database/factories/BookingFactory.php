<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('now', '+30 days');
        $duration = $this->faker->randomElement([30, 60, 90, 120]); // minutes
        $endTime = (clone $startTime)->modify("+{$duration} minutes");
        
        return [
            'shop_id' => \App\Models\Shop::factory(),
            'location_id' => \App\Models\Location::factory(),
            'service_id' => \App\Models\Service::factory(),
            'slot_start_utc' => $startTime,
            'slot_end_utc' => $endTime,
            'seats' => $this->faker->numberBetween(1, 3),
            'customer_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'cancelled', 'completed']),
        ];
    }
}
