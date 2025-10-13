<?php

namespace App\Jobs;

use App\Services\AvailabilityService;
use App\Services\ShopifyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecomputeAvailability implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $locationId,
        public string $variantId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ShopifyService $shopifyService, AvailabilityService $availabilityService): void
    {
        try {
            Log::info('Recomputing availability for inventory update', [
                'location_id' => $this->locationId,
                'variant_id' => $this->variantId
            ]);

            // Get current inventory level from Shopify
            $availableQuantity = $shopifyService->getInventoryForVariantAtLocation(
                $this->variantId,
                $this->locationId
            );

            // Update cached availability or trigger downstream events
            $this->updateAvailabilityCache($this->locationId, $this->variantId, $availableQuantity);

            // Bust availability cache for all services at this location
            $availabilityService->bustCapacityCache($this->locationId);

            Log::info('Availability recomputed successfully', [
                'location_id' => $this->locationId,
                'variant_id' => $this->variantId,
                'available_quantity' => $availableQuantity
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to recompute availability', [
                'location_id' => $this->locationId,
                'variant_id' => $this->variantId,
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Update availability cache or trigger downstream events
     */
    private function updateAvailabilityCache(string $locationId, string $variantId, ?int $availableQuantity): void
    {
        // Here you would implement your caching strategy
        // This could be:
        // 1. Update a Redis cache
        // 2. Update a database cache table
        // 3. Trigger events for other services
        // 4. Update booking availability in real-time

        // Example: Update a cache key
        $cacheKey = "inventory:{$locationId}:{$variantId}";
        cache()->put($cacheKey, $availableQuantity, now()->addHours(1));

        // Example: Trigger an event for real-time updates
        // event(new InventoryUpdated($locationId, $variantId, $availableQuantity));

        // Example: Update booking availability
        // $this->updateBookingAvailability($locationId, $variantId, $availableQuantity);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RecomputeAvailability job failed permanently', [
            'location_id' => $this->locationId,
            'variant_id' => $this->variantId,
            'error' => $exception->getMessage()
        ]);
    }
}
