<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\LocationSetting;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BookingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockShopifyService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Shopify service
        $this->mockShopifyService = Mockery::mock(ShopifyService::class);
        $this->app->instance(ShopifyService::class, $this->mockShopifyService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_a_booking()
    {
        // Create test data
        $location = Location::factory()->create([
            'timezone' => 'America/New_York'
        ]);
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 4,
        ]);

        // Mock inventory check
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->zeroOrMoreTimes()
            ->andReturn(10);

        $bookingData = [
            'location_id' => $location->id,
            'service_id' => $service->id,
            'slot_start_iso' => Carbon::now()->addHour()->setTimezone('America/New_York')->toISOString(),
            'seats' => 2,
            'customer' => [
                'name' => 'John Doe',
                'phone' => '555-1234',
                'email' => 'john@example.com',
            ],
        ];

        $response = $this->postJson('/api/bookings', $bookingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'slot_start',
                    'slot_end',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify booking was created
        $this->assertDatabaseHas('bookings', [
            'location_id' => $location->id,
            'service_id' => $service->id,
            'customer_name' => 'John Doe',
            'seats' => 2,
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_booking()
    {
        $response = $this->postJson('/api/bookings', []);

        $response->assertStatus(422);
        
        // Check the actual response structure
        $responseData = $response->json();
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertArrayHasKey('location_id', $responseData['errors']);
        $this->assertArrayHasKey('service_id', $responseData['errors']);
        $this->assertArrayHasKey('slot_start_iso', $responseData['errors']);
        $this->assertArrayHasKey('customer', $responseData['errors']);
    }

    /** @test */
    public function it_can_get_a_specific_booking()
    {
        // Create test data
        $location = Location::factory()->create([
            'timezone' => 'America/New_York'
        ]);
        $service = Service::factory()->create([
            'shop_id' => $location->shop_id,
            'title' => 'Oil Change',
            'duration_minutes' => 60,
            'price_cents' => 7500,
        ]);

        $booking = \App\Models\Booking::factory()->create([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'customer_name' => 'Jane Doe',
            'phone' => '555-5678',
            'email' => 'jane@example.com',
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/bookings/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'slot_start',
                    'slot_end',
                    'seats',
                    'customer' => [
                        'name',
                        'phone',
                        'email',
                    ],
                    'service' => [
                        'id',
                        'title',
                        'duration_minutes',
                        'price',
                    ],
                    'location' => [
                        'id',
                        'name',
                        'timezone',
                    ],
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'status' => 'confirmed',
                    'seats' => 1,
                    'customer' => [
                        'name' => 'Jane Doe',
                        'phone' => '555-5678',
                        'email' => 'jane@example.com',
                    ],
                    'service' => [
                        'id' => $service->id,
                        'title' => 'Oil Change',
                        'duration_minutes' => 60,
                        'price' => 75.00,
                    ],
                    'location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'timezone' => 'America/New_York',
                    ],
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_booking()
    {
        $response = $this->getJson('/api/bookings/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Booking not found'
            ]);
    }

    /** @test */
    public function it_handles_race_conditions_in_booking()
    {
        // Create test data with limited capacity
        $location = Location::factory()->create([
            'timezone' => 'America/New_York'
        ]);
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 1, // Only 1 seat
        ]);

        // Create existing booking that takes the only seat
        $slotStart = Carbon::now()->addHour()->setTimezone('America/New_York');
        \App\Models\Booking::factory()->create([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'slot_start_utc' => $slotStart->utc(),
            'slot_end_utc' => $slotStart->copy()->addMinutes(60)->utc(),
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        // Mock inventory check
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->zeroOrMoreTimes()
            ->andReturn(10);

        $bookingData = [
            'location_id' => $location->id,
            'service_id' => $service->id,
            'slot_start_iso' => $slotStart->toISOString(),
            'seats' => 1,
            'customer' => [
                'name' => 'Jane Doe',
                'phone' => '555-5678',
                'email' => 'jane@example.com',
            ],
        ];

        $response = $this->postJson('/api/bookings', $bookingData);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => 'Insufficient capacity'
            ]);
    }
}