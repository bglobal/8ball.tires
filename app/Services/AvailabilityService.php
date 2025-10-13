<?php

namespace App\Services;

use App\DTOs\AvailabilitySlot;
use App\DTOs\BookingRequest;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationSetting;
use App\Models\Service;
use App\Models\ServicePart;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AvailabilityService
{
    private array $circuitBreaker = [];
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_TIMEOUT = 300; // 5 minutes

    public function __construct(
        private ShopifyService $shopifyService
    ) {}

    /**
     * Get daily availability slots for a location and service
     */
    public function getDailySlots(int $locationId, int $serviceId, Carbon $date): array
    {
        // Only allow next 30 days
        $maxDate = Carbon::now()->addDays(30);
        if ($date->gt($maxDate)) {
            return [];
        }

        // Don't show past dates
        if ($date->lt(Carbon::today())) {
            return [];
        }

       $cacheKey = "availability:{$locationId}:{$serviceId}:" . $date->format('Y-m-d');
       return Cache::store('file')->remember($cacheKey, 300, function () use ($locationId, $serviceId, $date) {
           return $this->generateDailySlots($locationId, $serviceId, $date);
       });
    }

    /**
     * Lock and book a slot with race condition protection
     */
    public function lockAndBook(BookingRequest $bookingRequest): array
    {
        return DB::transaction(function () use ($bookingRequest) {
            // Recompute seatsLeft and inventory
            $availability = $this->checkSlotAvailability(
                $bookingRequest->locationId,
                $bookingRequest->serviceId,
                $bookingRequest->slotStartUtc,
                $bookingRequest->seats
            );

            if (!$availability['available']) {
                // Return appropriate status code based on reason
                $status = $availability['reason'] === 'Insufficient inventory' ? 422 : 409;
                return [
                    'success' => false,
                    'error' => $availability['reason'],
                    'status' => $status
                ];
            }

            // Get service and location settings for proper slot_end calculation
            $service = Service::findOrFail($bookingRequest->serviceId);
            $location = Location::with('settings')->findOrFail($bookingRequest->locationId);
            $slotDuration = max($service->duration_minutes, $location->settings->slot_duration_minutes);

            // Create the booking
            $booking = Booking::create([
                'shop_id' => $this->getShopIdForLocation($bookingRequest->locationId),
                'location_id' => $bookingRequest->locationId,
                'service_id' => $bookingRequest->serviceId,
                'slot_start_utc' => $bookingRequest->slotStartUtc,
                'slot_end_utc' => $bookingRequest->slotStartUtc->copy()->addMinutes($slotDuration),
                'seats' => $bookingRequest->seats,
                'customer_name' => $bookingRequest->customerName,
                'phone' => $bookingRequest->phone,
                'email' => $bookingRequest->email,
                'status' => 'confirmed',
            ]);

            // Bust cache
            $this->bustAvailabilityCache($bookingRequest->locationId, $bookingRequest->serviceId, $bookingRequest->slotStartUtc);

            return [
                'success' => true,
                'booking_id' => $booking->id,
                'status' => 201
            ];
        });
    }

    /**
     * Generate daily slots for a location and service
     */
    private function generateDailySlots(int $locationId, int $serviceId, Carbon $date): array
    {
        // Load location settings
        $location = Location::with('settings')->findOrFail($locationId);
        $settings = $location->settings;

        if (!$settings) {
            return [];
        }

        // Check if date is blacked out
        if ($this->isDateBlackedOut($locationId, $date)) {
            return [];
        }

        // Determine operating hours for the day
        $operatingHours = $this->getOperatingHours($settings, $date);

        if (!$operatingHours) {
            return [];
        }

        // Generate slots
        $slots = [];
        $currentTime = $date->copy()->setTimeFromTimeString($operatingHours['open']);
        $closeTime = $date->copy()->setTimeFromTimeString($operatingHours['close']);
        $service = Service::findOrFail($serviceId);
        $serviceDuration = $service->duration_minutes;

        while ($currentTime->copy()->addMinutes($serviceDuration)->lte($closeTime)) {
            // Use max of service duration and slot length for layout
            $slotDuration = max($serviceDuration, $settings->slot_duration_minutes);
            $slotEnd = $currentTime->copy()->addMinutes($slotDuration);

            // Calculate capacity
            $capacity = $this->calculateSlotCapacity($locationId, $currentTime, $slotEnd);


            if ($capacity['seatsLeft'] > 0) {
                // Check inventory
                $inventoryOk = $this->checkInventoryAvailability(
                    $serviceId,
                    $locationId,
                    $capacity['seatsLeft']
                );

                $slots[] = new AvailabilitySlot(
                    $currentTime->copy(),
                    $slotEnd,
                    $capacity['seatsLeft'],
                    $inventoryOk
                );
            }

            $currentTime->addMinutes($settings->slot_duration_minutes);
        }

        return array_map(fn($slot) => $slot->toArray(), $slots);
    }

    /**
     * Check if a date is blacked out
     */
    private function isDateBlackedOut(int $locationId, Carbon $date): bool
    {
        return Blackout::where('location_id', $locationId)
            ->whereDate('date', $date->format('Y-m-d'))
            ->exists();
    }

    /**
     * Get operating hours for a specific date
     */
    private function getOperatingHours(LocationSetting $settings, Carbon $date): ?array
    {
        $isWeekend = $date->isWeekend();

        if ($isWeekend && !$settings->is_weekend_open) {
            return null;
        }

        if ($isWeekend) {
            return [
                'open' => $settings->weekend_open_time->format('H:i:s'),
                'close' => $settings->weekend_close_time->format('H:i:s'),
            ];
        }

        return [
            'open' => $settings->open_time->format('H:i:s'),
            'close' => $settings->close_time->format('H:i:s'),
        ];
    }

    /**
     * Calculate slot capacity
     */
    private function calculateSlotCapacity(int $locationId, Carbon $slotStart, Carbon $slotEnd): array
    {
        // Get total capacity for the location
        $totalCapacity = $this->getLocationCapacity($locationId);

        // Calculate seats taken for overlapping slots
        $seatsTaken = Booking::where('location_id', $locationId)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($slotStart, $slotEnd) {
                $query->where(function ($q) use ($slotStart, $slotEnd) {
                    // Booking starts before slot ends and ends after slot starts
                    $q->where('slot_start_utc', '<', $slotEnd)
                      ->where('slot_end_utc', '>', $slotStart);
                });
            })
            ->sum('seats');

        return [
            'seatsLeft' => max(0, $totalCapacity - $seatsTaken),
            'seatsTaken' => $seatsTaken,
            'totalCapacity' => $totalCapacity,
        ];
    }

    /**
     * Get location capacity (from settings or resources)
     */
    private function getLocationCapacity(int $locationId): int
    {
        $location = Location::with(['settings', 'resources'])->findOrFail($locationId);

        // Use capacity_per_slot from settings if available
        if ($location->settings && $location->settings->capacity_per_slot > 0) {
            return $location->settings->capacity_per_slot;
        }

        // Otherwise, sum up resources
        return $location->resources->sum('seats');
    }

    /**
     * Check inventory availability for a service
     */
    private function checkInventoryAvailability(int $serviceId, int $locationId, int $seatsRequested): bool
    {
        try {

            $serviceParts = ServicePart::where('service_id', $serviceId)->get();

            if ($serviceParts->count() <= 0) {
                return true; // No parts required
            }

            $location = Location::findOrFail($locationId);

            $locationGid = $location->shopify_location_gid;

            // Check if we have cached inventory data
            $cacheKey = "inventory:{$locationGid}:" . $serviceParts->pluck('shopify_variant_gid')->sort()->implode(',');
            $cachedInventory = Cache::store('file')->get($cacheKey);

            if ($cachedInventory === null) {
                // Fetch inventory for all variants at once
                $variantGids = $serviceParts->pluck('shopify_variant_gid')->toArray();
                $inventoryData = $this->getInventoryForVariants($variantGids, $locationGid);

                // Cache for 5 minutes using file cache (no size limit)
                Cache::store('file')->put($cacheKey, $inventoryData, 300);
            } else {
                $inventoryData = $cachedInventory;
            }

            foreach ($serviceParts as $part) {
                $requiredQty = $seatsRequested * $part->qty_per_service;
                $availableQty = $inventoryData[$part->shopify_variant_gid] ?? null;

                if ($availableQty === null || $availableQty < $requiredQty) {
                    Log::info('Insufficient inventory for service part', [
                        'service_id' => $serviceId,
                        'variant_gid' => $part->shopify_variant_gid,
                        'required_qty' => $requiredQty,
                        'available_qty' => $availableQty,
                        'location_gid' => $locationGid
                    ]);

                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error checking inventory availability', [
                'service_id' => $serviceId,
                'location_id' => $locationId,
                'seats_requested' => $seatsRequested,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check slot availability for booking with race condition protection
     */
    private function checkSlotAvailability(int $locationId, int $serviceId, Carbon $slotStartUtc, int $seats): array
    {
        $service = Service::findOrFail($serviceId);
        $slotEndUtc = $slotStartUtc->copy()->addMinutes($service->duration_minutes);

        // Use row-level locking to prevent race conditions
        $capacity = $this->calculateSlotCapacityWithLock($locationId, $slotStartUtc, $slotEndUtc);

        if ($capacity['seatsLeft'] < $seats) {
            return [
                'available' => false,
                'reason' => 'Insufficient capacity'
            ];
        }

        $inventoryOk = $this->checkInventoryAvailability($serviceId, $locationId, $seats);

        if (!$inventoryOk) {
            return [
                'available' => false,
                'reason' => 'Insufficient inventory'
            ];
        }

        return ['available' => true];
    }

    /**
     * Calculate slot capacity with row-level locking to prevent race conditions
     */
    private function calculateSlotCapacityWithLock(int $locationId, Carbon $slotStart, Carbon $slotEnd): array
    {
        // Lock all existing bookings for this slot to prevent race conditions
        $existingBookings = DB::table('bookings')
            ->where('location_id', $locationId)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($slotStart, $slotEnd) {
                $query->where(function ($q) use ($slotStart, $slotEnd) {
                    // Booking starts before slot ends and ends after slot starts
                    $q->where('slot_start_utc', '<', $slotEnd)
                      ->where('slot_end_utc', '>', $slotStart);
                });
            })
            ->lockForUpdate()
            ->get();

        // Calculate seats taken
        $seatsTaken = $existingBookings->sum('seats');

        // Get total capacity for the location
        $totalCapacity = $this->getLocationCapacity($locationId);

        return [
            'seatsLeft' => max(0, $totalCapacity - $seatsTaken),
            'seatsTaken' => $seatsTaken,
            'totalCapacity' => $totalCapacity,
        ];
    }

    /**
     * Generate slot key for locking
     */
    private function generateSlotKey(int $locationId, Carbon $slotStartUtc): string
    {
        return "{$locationId}:" . $slotStartUtc->format('Y-m-d-H-i');
    }

    /**
     * Get shop ID for a location
     */
    private function getShopIdForLocation(int $locationId): int
    {
        return Location::findOrFail($locationId)->shop_id;
    }

    /**
     * Bust availability cache
     */
    private function bustAvailabilityCache(int $locationId, int $serviceId, Carbon $date): void
    {
        $cacheKey = "availability:{$locationId}:{$serviceId}:" . $date->format('Y-m-d');
        Cache::store('file')->forget($cacheKey);
    }

    /**
     * Get inventory for multiple variants with error handling
     */
    private function getInventoryForVariants(array $variantGids, string $locationGid): array
    {
        $inventoryData = [];

        // Check circuit breaker
        if ($this->isCircuitBreakerOpen($locationGid)) {
            Log::warning('Circuit breaker open for location', ['location_gid' => $locationGid]);
            return array_fill_keys($variantGids, null);
        }

        try {
            // Process variants in smaller batches to avoid overwhelming the API
            $batchSize = 3;
            $batches = array_chunk($variantGids, $batchSize);

            foreach ($batches as $batch) {
                foreach ($batch as $variantGid) {
                    try {
                        $inventoryData[$variantGid] = $this->shopifyService->getInventoryForVariantAtLocation(
                            $variantGid,
                            $locationGid
                        );
                        $this->recordSuccess($locationGid);
                    } catch (\Exception $e) {
                        $this->recordFailure($locationGid);
                        Log::warning('Failed to get inventory for variant', [
                            'variant_gid' => $variantGid,
                            'location_gid' => $locationGid,
                            'error' => $e->getMessage()
                        ]);
                        $inventoryData[$variantGid] = null;
                    }
                }

                // Small delay between batches to respect rate limits
                if (count($batches) > 1) {
                    usleep(200000); // 0.2 second delay
                }
            }

            return $inventoryData;
        } catch (\Exception $e) {
            $this->recordFailure($locationGid);
            Log::error('Failed to get inventory for variants', [
                'variant_gids' => $variantGids,
                'location_gid' => $locationGid,
                'error' => $e->getMessage()
            ]);

            // Return null for all variants on error
            return array_fill_keys($variantGids, null);
        }
    }

    /**
     * Check if circuit breaker is open for a location
     */
    private function isCircuitBreakerOpen(string $locationGid): bool
    {
        if (!isset($this->circuitBreaker[$locationGid])) {
            return false;
        }

        $breaker = $this->circuitBreaker[$locationGid];

        if ($breaker['state'] === 'open') {
            if (time() - $breaker['last_failure'] > self::CIRCUIT_BREAKER_TIMEOUT) {
                // Reset circuit breaker
                $this->circuitBreaker[$locationGid] = [
                    'state' => 'closed',
                    'failure_count' => 0,
                    'last_failure' => 0
                ];
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Record a successful API call
     */
    private function recordSuccess(string $locationGid): void
    {
        if (!isset($this->circuitBreaker[$locationGid])) {
            $this->circuitBreaker[$locationGid] = [
                'state' => 'closed',
                'failure_count' => 0,
                'last_failure' => 0
            ];
        }

        $this->circuitBreaker[$locationGid]['failure_count'] = 0;
    }

    /**
     * Record a failed API call
     */
    private function recordFailure(string $locationGid): void
    {
        if (!isset($this->circuitBreaker[$locationGid])) {
            $this->circuitBreaker[$locationGid] = [
                'state' => 'closed',
                'failure_count' => 0,
                'last_failure' => 0
            ];
        }

        $this->circuitBreaker[$locationGid]['failure_count']++;
        $this->circuitBreaker[$locationGid]['last_failure'] = time();

        if ($this->circuitBreaker[$locationGid]['failure_count'] >= self::CIRCUIT_BREAKER_THRESHOLD) {
            $this->circuitBreaker[$locationGid]['state'] = 'open';
            Log::warning('Circuit breaker opened for location', [
                'location_gid' => $locationGid,
                'failure_count' => $this->circuitBreaker[$locationGid]['failure_count']
            ]);
        }
    }

    /**
     * Bust cache for all dates when inventory changes
     */
    public function bustInventoryCache(int $locationId, int $serviceId): void
    {
        // Bust cache for the next 30 days
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now()->addDays($i);
            $this->bustAvailabilityCache($locationId, $serviceId, $date);
        }
    }

    /**
     * Bust cache for all services at a location when capacity changes
     */
    public function bustCapacityCache(int $locationId): void
    {
        $services = Service::where('shop_id', Location::findOrFail($locationId)->shop_id)->pluck('id');

        foreach ($services as $serviceId) {
            $this->bustInventoryCache($locationId, $serviceId);
        }
    }
}
