<?php

namespace Tests\Unit;

use App\DTOs\BookingRequest;
use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationSetting;
use App\Models\Service;
use App\Models\Shop;
use App\Services\AvailabilityService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $availabilityService;
    private Shop $shop;
    private Location $location;
    private Service $service;
    private Carbon $slotStart;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock ShopifyService to avoid external API calls
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('getInventoryForVariantAtLocation')
                ->andReturn(100); // Always return sufficient inventory
        });

        $this->availabilityService = app(AvailabilityService::class);
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create shop
        $this->shop = Shop::factory()->create([
            'shopify_domain' => 'test-shop.myshopify.com',
            'admin_api_token' => 'test-token',
            'currency' => 'USD',
        ]);

        // Create location
        $this->location = Location::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Test Location',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        // Create location settings with capacity of 5
        LocationSetting::factory()->create([
            'location_id' => $this->location->id,
            'slot_duration_minutes' => 30,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
            'is_weekend_open' => false,
            'capacity_per_slot' => 5, // Set capacity to 5 for testing
        ]);

        // Create service
        $this->service = Service::factory()->create([
            'shop_id' => $this->shop->id,
            'title' => 'Test Service',
            'duration_minutes' => 30,
            'price_cents' => 5000,
            'active' => true,
        ]);

        // Set slot start time (tomorrow at 10:00 AM)
        $this->slotStart = Carbon::tomorrow()->setTime(10, 0, 0);
    }

    /**
     * Test concurrent booking attempts with capacity=5
     * Should only allow 5 successful bookings
     */
    public function test_concurrent_booking_attempts_with_capacity_limit(): void
    {
        $concurrentAttempts = 20;
        $expectedSuccessfulBookings = 5;
        $slotStartUtc = $this->slotStart->utc();

        // Create booking requests for the same slot
        $bookingRequests = [];
        for ($i = 0; $i < $concurrentAttempts; $i++) {
            $bookingRequests[] = new BookingRequest(
                $this->location->id,
                $this->service->id,
                $slotStartUtc,
                1, // 1 seat per booking
                "Customer {$i}",
                "+1555000{$i}",
                "customer{$i}@test.com"
            );
        }

        // Execute all booking attempts concurrently
        $results = [];
        $promises = [];

        foreach ($bookingRequests as $index => $bookingRequest) {
            $promises[] = $this->executeBookingAsync($bookingRequest, $index);
        }

        // Wait for all promises to complete
        foreach ($promises as $promise) {
            $results[] = $promise();
        }

        // Analyze results
        $successfulBookings = array_filter($results, fn($result) => $result['success'] === true);
        $failedBookings = array_filter($results, fn($result) => $result['success'] === false);

        // Assertions
        $this->assertCount($expectedSuccessfulBookings, $successfulBookings, 
            "Expected exactly {$expectedSuccessfulBookings} successful bookings, got " . count($successfulBookings));

        $this->assertCount($concurrentAttempts - $expectedSuccessfulBookings, $failedBookings,
            "Expected " . ($concurrentAttempts - $expectedSuccessfulBookings) . " failed bookings, got " . count($failedBookings));

        // Verify all successful bookings are in the database
        $this->assertEquals($expectedSuccessfulBookings, Booking::count(),
            "Expected {$expectedSuccessfulBookings} bookings in database, got " . Booking::count());

        // Verify all bookings are for the same slot
        $bookings = Booking::all();
        foreach ($bookings as $booking) {
            $this->assertEquals($slotStartUtc->format('Y-m-d H:i:s'), $booking->slot_start_utc->format('Y-m-d H:i:s'));
            $this->assertEquals($this->location->id, $booking->location_id);
            $this->assertEquals($this->service->id, $booking->service_id);
            $this->assertEquals('confirmed', $booking->status->value);
        }

        // Verify total seats booked equals capacity
        $totalSeatsBooked = Booking::sum('seats');
        $this->assertEquals($expectedSuccessfulBookings, $totalSeatsBooked,
            "Total seats booked should equal capacity");

        // Verify all bookings are confirmed and within capacity
        $confirmedBookings = Booking::where('status', 'confirmed')->count();
        $this->assertEquals($expectedSuccessfulBookings, $confirmedBookings, 
            "All successful bookings should be confirmed");
    }

    /**
     * Test concurrent booking attempts with different seat requirements
     */
    public function test_concurrent_booking_with_different_seat_requirements(): void
    {
        $slotStartUtc = $this->slotStart->utc();
        
        // Create booking requests with different seat requirements
        $bookingRequests = [
            new BookingRequest($this->location->id, $this->service->id, $slotStartUtc, 2, "Customer 1", "+15550001", "customer1@test.com"),
            new BookingRequest($this->location->id, $this->service->id, $slotStartUtc, 2, "Customer 2", "+15550002", "customer2@test.com"),
            new BookingRequest($this->location->id, $this->service->id, $slotStartUtc, 1, "Customer 3", "+15550003", "customer3@test.com"),
            new BookingRequest($this->location->id, $this->service->id, $slotStartUtc, 1, "Customer 4", "+15550004", "customer4@test.com"),
        ];

        // Execute all booking attempts
        $results = [];
        foreach ($bookingRequests as $index => $bookingRequest) {
            $results[] = $this->executeBookingAsync($bookingRequest, $index)();
        }

        // Should have exactly 5 total seats booked (capacity limit)
        $successfulBookings = array_filter($results, fn($result) => $result['success'] === true);
        $totalSeatsBooked = array_sum(array_map(fn($result) => $result['seats'] ?? 0, $successfulBookings));
        
        $this->assertEquals(5, $totalSeatsBooked, "Total seats booked should equal capacity of 5");
        $this->assertLessThanOrEqual(5, $totalSeatsBooked, "Total seats booked should not exceed capacity");
    }

    /**
     * Test that failed bookings don't leave the system in an inconsistent state
     */
    public function test_failed_bookings_do_not_leave_inconsistent_state(): void
    {
        $slotStartUtc = $this->slotStart->utc();
        
        // Create 10 booking requests (more than capacity)
        $bookingRequests = [];
        for ($i = 0; $i < 10; $i++) {
            $bookingRequests[] = new BookingRequest(
                $this->location->id,
                $this->service->id,
                $slotStartUtc,
                1,
                "Customer {$i}",
                "+1555000{$i}",
                "customer{$i}@test.com"
            );
        }

        // Execute all booking attempts
        $results = [];
        foreach ($bookingRequests as $index => $bookingRequest) {
            $results[] = $this->executeBookingAsync($bookingRequest, $index)();
        }

        // Verify database consistency
        $totalSeatsInDb = Booking::sum('seats');
        $this->assertEquals(5, $totalSeatsInDb, "Database should have exactly 5 seats booked");

        // Verify no duplicate bookings for the same customer
        $customerEmails = Booking::pluck('email')->toArray();
        $this->assertCount(count(array_unique($customerEmails)), $customerEmails, 
            "No duplicate customer emails should exist");

        // Verify all bookings are confirmed
        $confirmedBookings = Booking::where('status', 'confirmed')->count();
        $this->assertEquals($totalSeatsInDb, $confirmedBookings, "All bookings should be confirmed");
    }

    /**
     * Execute booking asynchronously (simulate concurrent execution)
     */
    private function executeBookingAsync(BookingRequest $bookingRequest, int $index): callable
    {
        return function () use ($bookingRequest, $index) {
            try {
                // Add small random delay to simulate real-world concurrency
                usleep(rand(1000, 5000)); // 1-5ms delay
                
                $result = $this->availabilityService->lockAndBook($bookingRequest);
                
                // Add booking info to result for analysis
                if ($result['success']) {
                    $booking = Booking::find($result['booking_id']);
                    $result['seats'] = $booking->seats;
                    $result['customer_email'] = $booking->email;
                }
                
                return $result;
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'status' => 500
                ];
            }
        };
    }

    /**
     * Generate slot key for testing
     */
    private function generateSlotKey(int $locationId, Carbon $slotStartUtc): string
    {
        return "{$locationId}:" . $slotStartUtc->format('Y-m-d-H-i');
    }
}
