<?php

namespace Tests\Feature;

use App\Models\Blackout;
use App\Models\Location;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlackoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected Location $location;

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
    }

    /** @test */
    public function it_returns_blackout_dates_for_valid_location()
    {
        // Create 3 blackout records for the test location
        $blackoutDates = [
            '2025-10-05',
            '2025-10-11',
            '2025-10-15'
        ];

        foreach ($blackoutDates as $date) {
            Blackout::create([
                'location_id' => $this->location->id,
                'date' => $date,
                'reason' => 'Test blackout for ' . $date
            ]);
        }

        // Make API request
        $response = $this->getJson("/api/blackout/{$this->location->id}");

        // Assertions
        $response->assertStatus(200);
        $response->assertJson($blackoutDates);
        $response->assertJsonCount(3);
    }

    /** @test */
    public function it_returns_empty_array_when_no_blackouts_exist()
    {
        $response = $this->getJson("/api/blackout/{$this->location->id}");

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    /** @test */
    public function it_returns_422_when_location_id_is_invalid()
    {
        $response = $this->getJson('/api/blackout/999');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id']);
    }

    /** @test */
    public function it_returns_422_when_location_id_is_not_integer()
    {
        $response = $this->getJson('/api/blackout/abc');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id']);
    }

    /** @test */
    public function it_returns_dates_in_correct_format()
    {
        // Create a blackout with a specific date
        Blackout::create([
            'location_id' => $this->location->id,
            'date' => '2025-12-25',
            'reason' => 'Christmas Day'
        ]);

        $response = $this->getJson("/api/blackout/{$this->location->id}");

        $response->assertStatus(200);
        $response->assertJson(['2025-12-25']);
        
        // Verify the date format is exactly YYYY-MM-DD
        $data = $response->json();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data[0]);
    }

    /** @test */
    public function it_orders_dates_chronologically()
    {
        // Create blackouts in random order
        $dates = ['2025-12-25', '2025-10-05', '2025-11-15'];
        
        foreach ($dates as $date) {
            Blackout::create([
                'location_id' => $this->location->id,
                'date' => $date,
                'reason' => 'Test blackout'
            ]);
        }

        $response = $this->getJson("/api/blackout/{$this->location->id}");

        $response->assertStatus(200);
        
        // Expected order: 2025-10-05, 2025-11-15, 2025-12-25
        $expectedOrder = ['2025-10-05', '2025-11-15', '2025-12-25'];
        $response->assertJson($expectedOrder);
    }
}
