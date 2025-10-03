<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServicePart;
use App\Models\LocationSetting;
use App\Models\Resource;
use App\Models\Booking;
use App\Models\Blackout;
use App\BookingStatus;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create a sample shop
        $shop = Shop::factory()->create([
            'shopify_domain' => '8balltires.myshopify.com',
            'admin_api_token' => 'shpat_1234567890abcdef',
            'currency' => 'USD',
        ]);

        // Create 2 locations
        $locations = Location::factory()->count(2)->create([
            'shop_id' => $shop->id,
        ]);

        // Create location settings and resources for each location
        foreach ($locations as $location) {
            LocationSetting::factory()->create([
                'location_id' => $location->id,
            ]);

            // Create 2-3 resources per location
            Resource::factory()->count(rand(2, 3))->create([
                'location_id' => $location->id,
            ]);
        }

        // Create 3 specific services
        $services = [
            [
                'title' => 'Oil Change',
                'slug' => 'oil-change',
                'duration_minutes' => 30,
                'price_cents' => 2999, // $29.99
                'parts' => [
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567890', 'qty_per_service' => 1],
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567891', 'qty_per_service' => 1],
                ]
            ],
            [
                'title' => 'Brake Pad Install',
                'slug' => 'brake-pad-install',
                'duration_minutes' => 60,
                'price_cents' => 7999, // $79.99
                'parts' => [
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567892', 'qty_per_service' => 2],
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567893', 'qty_per_service' => 1],
                ]
            ],
            [
                'title' => 'Chain/Sprocket',
                'slug' => 'chain-sprocket',
                'duration_minutes' => 90,
                'price_cents' => 12999, // $129.99
                'parts' => [
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567894', 'qty_per_service' => 1],
                    ['shopify_variant_gid' => 'gid://shopify/ProductVariant/1234567895', 'qty_per_service' => 1],
                ]
            ],
        ];

        foreach ($services as $serviceData) {
            $service = Service::factory()->create([
                'shop_id' => $shop->id,
                'title' => $serviceData['title'],
                'slug' => $serviceData['slug'],
                'duration_minutes' => $serviceData['duration_minutes'],
                'price_cents' => $serviceData['price_cents'],
            ]);

            // Create service parts (BOM)
            foreach ($serviceData['parts'] as $part) {
                ServicePart::factory()->create([
                    'service_id' => $service->id,
                    'shopify_variant_gid' => $part['shopify_variant_gid'],
                    'qty_per_service' => $part['qty_per_service'],
                ]);
            }
        }

        // Create 20 sample bookings
        Booking::factory()->count(20)->create([
            'shop_id' => $shop->id,
            'location_id' => $locations->random()->id,
            'service_id' => Service::inRandomOrder()->first()->id,
        ]);

        // Create 5 blackouts
        Blackout::factory()->count(5)->create([
            'location_id' => $locations->random()->id,
        ]);
    }
}