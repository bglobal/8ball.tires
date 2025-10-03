<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationSetting;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected Location $location;
    protected LocationSetting $locationSettings;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a shop first
        $shop = Shop::create([
            'shopify_domain' => 'test-shop.myshopify.com',
            'admin_api_token' => 'test_token'
        ]);

        // Create a test location
        $this->location = Location::create([
            'shop_id' => $shop->id,
            'shopify_location_gid' => 'gid://shopify/Location/123456',
            'name' => 'Test Location',
            'timezone' => 'America/New_York',
            'is_active' => true
        ]);

        // Create location settings
        $this->locationSettings = LocationSetting::create([
            'location_id' => $this->location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
            'is_weekend_open' => true,
            'weekend_open_time' => '09:00:00',
            'weekend_close_time' => '17:00:00',
            'capacity_per_slot' => 3
        ]);
    }

    /** @test */
    public function it_returns_location_details_with_settings_for_valid_id()
    {
        $response = $this->getJson("/api/location/{$this->location->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $this->location->id,
                'name' => 'Test Location',
                'timezone' => 'America/New_York',
                'is_active' => true,
                'settings' => [
                    'slot_duration_minutes' => 60,
                    'open_time' => '08:00',
                    'close_time' => '18:00',
                    'is_weekend_open' => true,
                    'weekend_open_time' => '09:00',
                    'weekend_close_time' => '17:00',
                    'capacity_per_slot' => 3
                ]
            ]
        ]);
    }

    /** @test */
    public function it_returns_location_without_settings_when_none_exist()
    {
        // Create a location without settings
        $shop = Shop::first();
        $locationWithoutSettings = Location::create([
            'shop_id' => $shop->id,
            'shopify_location_gid' => 'gid://shopify/Location/789',
            'name' => 'Location Without Settings',
            'timezone' => 'America/Los_Angeles',
            'is_active' => true
        ]);

        $response = $this->getJson("/api/location/{$locationWithoutSettings->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $locationWithoutSettings->id,
                'name' => 'Location Without Settings',
                'timezone' => 'America/Los_Angeles',
                'is_active' => true,
                'settings' => null
            ]
        ]);
    }

    /** @test */
    public function it_returns_404_for_invalid_location_id()
    {
        $response = $this->getJson('/api/location/999');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id']);
    }

    /** @test */
    public function it_returns_404_for_inactive_location()
    {
        // Create an inactive location
        $shop = Shop::first();
        $inactiveLocation = Location::create([
            'shop_id' => $shop->id,
            'shopify_location_gid' => 'gid://shopify/Location/inactive',
            'name' => 'Inactive Location',
            'timezone' => 'America/Chicago',
            'is_active' => false
        ]);

        $response = $this->getJson("/api/location/{$inactiveLocation->id}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Location not found or inactive'
        ]);
    }

    /** @test */
    public function it_returns_422_for_non_integer_id()
    {
        $response = $this->getJson('/api/location/abc');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id']);
    }

    /** @test */
    public function it_returns_422_for_missing_id()
    {
        $response = $this->getJson('/api/location/');

        $response->assertStatus(404); // Laravel route not found
    }

    /** @test */
    public function it_returns_properly_formatted_time_fields()
    {
        $response = $this->getJson("/api/location/{$this->location->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('settings', $data);
        $this->assertNotNull($data['settings']);
        
        // Check that time fields are in HH:MM format
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $data['settings']['open_time']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $data['settings']['close_time']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $data['settings']['weekend_open_time']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $data['settings']['weekend_close_time']);
    }

    /** @test */
    public function it_returns_all_expected_location_fields()
    {
        $response = $this->getJson("/api/location/{$this->location->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check location fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('timezone', $data);
        $this->assertArrayHasKey('is_active', $data);
        $this->assertArrayHasKey('settings', $data);
        
        // Check settings fields
        $settings = $data['settings'];
        $this->assertArrayHasKey('slot_duration_minutes', $settings);
        $this->assertArrayHasKey('open_time', $settings);
        $this->assertArrayHasKey('close_time', $settings);
        $this->assertArrayHasKey('is_weekend_open', $settings);
        $this->assertArrayHasKey('weekend_open_time', $settings);
        $this->assertArrayHasKey('weekend_close_time', $settings);
        $this->assertArrayHasKey('capacity_per_slot', $settings);
    }
}
