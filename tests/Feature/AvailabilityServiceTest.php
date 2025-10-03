<?php

namespace Tests\Feature;

use App\DTOs\BookingRequest;
use App\Models\Location;
use App\Models\LocationSetting;
use App\Models\Service;
use App\Models\ServicePart;
use App\Services\AvailabilityService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $availabilityService;
    private $mockShopifyService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Shopify service
        $this->mockShopifyService = Mockery::mock(ShopifyService::class);
        $this->app->instance(ShopifyService::class, $this->mockShopifyService);

        $this->availabilityService = new AvailabilityService($this->mockShopifyService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_daily_slots_for_a_location_and_service()
    {
        // Create test data
        $location = Location::factory()->create();
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 4,
        ]);

        // Mock inventory check (won't be called if no service parts)
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->zeroOrMoreTimes()
            ->andReturn(10); // Sufficient inventory

        $date = Carbon::today()->addDay();
        $slots = $this->availabilityService->getDailySlots($location->id, $service->id, $date);

        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);

        // Check slot structure
        $slot = $slots[0];
        $this->assertArrayHasKey('slotStart', $slot);
        $this->assertArrayHasKey('slotEnd', $slot);
        $this->assertArrayHasKey('seatsLeft', $slot);
        $this->assertArrayHasKey('inventoryOk', $slot);
        $this->assertEquals(4, $slot['seatsLeft']); // Full capacity
        $this->assertTrue($slot['inventoryOk']);
    }

    /** @test */
    public function it_returns_empty_slots_for_blacked_out_dates()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 4,
        ]);

        // Create blackout for today
        $blackout = \App\Models\Blackout::factory()->create([
            'location_id' => $location->id,
            'date' => Carbon::today()->format('Y-m-d'),
        ]);

        // Debug: Check if blackout was created
        $this->assertDatabaseHas('blackouts', [
            'location_id' => $location->id,
        ]);

        $slots = $this->availabilityService->getDailySlots($location->id, $service->id, Carbon::today());

        $this->assertIsArray($slots);
        $this->assertEmpty($slots, 'Expected empty slots for blacked out date, but got: ' . json_encode($slots));
    }

    /** @test */
    public function it_calculates_capacity_correctly_with_existing_bookings()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 4,
        ]);

        // Create overlapping booking
        $slotStart = Carbon::today()->addDay()->setTime(10, 0);
        \App\Models\Booking::factory()->create([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'slot_start_utc' => $slotStart,
            'slot_end_utc' => $slotStart->copy()->addMinutes(60),
            'seats' => 2,
            'status' => 'confirmed',
        ]);

        // Mock inventory check
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->andReturn(10);

        $slots = $this->availabilityService->getDailySlots($location->id, $service->id, $slotStart);

        // Find the slot that overlaps with our booking
        $overlappingSlot = collect($slots)->first(function ($slot) use ($slotStart) {
            $slotTime = Carbon::parse($slot['slotStart']);
            return $slotTime->format('H:i') === '10:00';
        });

        $this->assertNotNull($overlappingSlot);
        $this->assertEquals(2, $overlappingSlot['seatsLeft']); // 4 - 2 = 2
    }

    /** @test */
    public function it_checks_inventory_availability_correctly()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        // Create service parts
        ServicePart::factory()->create([
            'service_id' => $service->id,
            'shopify_variant_gid' => 'gid://shopify/ProductVariant/123',
            'qty_per_service' => 2,
        ]);

        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 4,
        ]);

        // Mock insufficient inventory
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->with('gid://shopify/ProductVariant/123', $location->shopify_location_gid)
            ->andReturn(3); // Not enough for 2 seats (need 4)

        $date = Carbon::today()->addDay();
        $slots = $this->availabilityService->getDailySlots($location->id, $service->id, $date);

        $this->assertIsArray($slots);
        
        // All slots should have inventoryOk = false
        foreach ($slots as $slot) {
            $this->assertFalse($slot['inventoryOk']);
        }
    }

    /** @test */
    public function it_can_book_a_slot_successfully()
    {
        $location = Location::factory()->create();
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
            ->andReturn(10);

        $slotStart = Carbon::now()->addHour();
        $bookingRequest = new BookingRequest(
            $location->id,
            $service->id,
            $slotStart,
            2,
            'John Doe',
            '555-1234',
            'john@example.com'
        );

        $result = $this->availabilityService->lockAndBook($bookingRequest);

        $this->assertTrue($result['success']);
        $this->assertEquals(201, $result['status']);
        $this->assertArrayHasKey('booking_id', $result);

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
    public function it_handles_race_conditions_in_booking()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create(['shop_id' => $location->shop_id]);
        
        LocationSetting::factory()->create([
            'location_id' => $location->id,
            'slot_duration_minutes' => 60,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'capacity_per_slot' => 1, // Only 1 seat available
        ]);

        // Create a booking that takes the only seat
        $slotStart = Carbon::now()->addHour();
        \App\Models\Booking::factory()->create([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'slot_start_utc' => $slotStart,
            'slot_end_utc' => $slotStart->copy()->addMinutes(60),
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        // Mock inventory check
        $this->mockShopifyService->shouldReceive('getInventoryForVariantAtLocation')
            ->andReturn(10);

        $bookingRequest = new BookingRequest(
            $location->id,
            $service->id,
            $slotStart,
            1,
            'Jane Doe',
            '555-5678',
            'jane@example.com'
        );

        $result = $this->availabilityService->lockAndBook($bookingRequest);

        $this->assertFalse($result['success']);
        $this->assertEquals(409, $result['status']);
        $this->assertStringContainsString('Insufficient capacity', $result['error']);
    }

    /** @test */
    public function it_caches_availability_responses()
    {
        $location = Location::factory()->create();
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
            ->zeroOrMoreTimes() // May or may not be called depending on service parts
            ->andReturn(10);

        $date = Carbon::today()->addDay();

        // First call
        $slots1 = $this->availabilityService->getDailySlots($location->id, $service->id, $date);
        
        // Second call (should use cache)
        $slots2 = $this->availabilityService->getDailySlots($location->id, $service->id, $date);

        $this->assertEquals($slots1, $slots2);
    }

    /** @test */
    public function it_busts_cache_when_capacity_changes()
    {
        $location = Location::factory()->create();
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
            ->zeroOrMoreTimes() // May or may not be called depending on service parts
            ->andReturn(10);

        $date = Carbon::today()->addDay();

        // First call
        $this->availabilityService->getDailySlots($location->id, $service->id, $date);
        
        // Bust cache
        $this->availabilityService->bustCapacityCache($location->id);
        
        // Second call (should not use cache)
        $this->availabilityService->getDailySlots($location->id, $service->id, $date);
    }
}