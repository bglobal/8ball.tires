<?php

namespace App\Jobs;

use App\Models\Location;
use App\Models\Service;
use App\Models\ServicePart;
use App\Services\ShopifyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopSync implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $shopifyService = app(ShopifyService::class);
            
            // Sync locations from Shopify
            $this->syncLocations($shopifyService);
            
            // Sync products/variants from Shopify
            $this->syncProducts($shopifyService);
            
            // Update last sync timestamp
            Cache::put('shopify_last_sync', now()->toISOString(), now()->addHours(24));
            
            Log::info('Shopify sync completed successfully');
            
        } catch (\Exception $e) {
            Log::error('Shopify sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync locations from Shopify
     */
    private function syncLocations(ShopifyService $shopifyService): void
    {
        $shopifyLocations = $shopifyService->getLocations();
        
        // Get the first shop ID (assuming single shop for now)
        $shopId = \App\Models\Shop::first()?->id;
        if (!$shopId) {
            Log::error('No shop found. Please create a shop first.');
            return;
        }
        
        foreach ($shopifyLocations as $locationEdge) {
            $shopifyLocation = $locationEdge['node'];
            
            Location::updateOrCreate(
                ['shopify_location_gid' => $shopifyLocation['id']],
                [
                    'shop_id' => $shopId,
                    'name' => $shopifyLocation['name'],
                    'timezone' => 'America/New_York', // Default timezone, should be configurable
                    'is_active' => $shopifyLocation['isActive'] ?? true,
                ]
            );
        }
        
        Log::info('Synced ' . count($shopifyLocations) . ' locations from Shopify');
    }

    /**
     * Sync products and variants from Shopify
     */
    private function syncProducts(ShopifyService $shopifyService): void
    {
        try {
            // Get the first shop ID
            $shopId = \App\Models\Shop::first()?->id;
            if (!$shopId) {
                Log::error('No shop found. Please create a shop first.');
                return;
            }

            // Fetch products from Shopify
            $products = $shopifyService->getProducts();
            
            $syncedCount = 0;
            $servicePartsCount = 0;
            
            foreach ($products as $productEdge) {
                $product = $productEdge['node'];
                
                // Check if this product should be treated as a service
                // You can add logic here to filter products based on tags, product type, etc.
                if ($this->isServiceProduct($product)) {
                    // Create or update service
                    $service = Service::updateOrCreate(
                        ['shopify_product_id' => $product['id']],
                        [
                            'shop_id' => $shopId,
                            'title' => $product['title'],
                            'slug' => \Str::slug($product['title']),
                            'duration_minutes' => $this->extractDurationFromProduct($product),
                            'price_cents' => $this->extractPriceFromProduct($product),
                            'active' => $product['status'] === 'ACTIVE',
                            'shopify_variant_gid' => $this->getMainVariantGid($product),
                        ]
                    );
                    
                    $syncedCount++;
                    
                    // Sync variants as service parts
                    if (isset($product['variants']['edges'])) {
                        foreach ($product['variants']['edges'] as $variantEdge) {
                            $variant = $variantEdge['node'];
                            
                            // Skip the main variant if it's the same as the service variant
                            if ($variant['id'] !== $service->shopify_variant_gid) {
                                ServicePart::updateOrCreate(
                                    ['shopify_variant_gid' => $variant['id']],
                                    [
                                        'service_id' => $service->id,
                                        'qty_per_service' => 1, // Default quantity
                                        'product_title' => $variant['title'] ?: $product['title'],
                                    ]
                                );
                                $servicePartsCount++;
                            }
                        }
                    }
                }
            }
            
            Log::info("Synced {$syncedCount} services and {$servicePartsCount} service parts from Shopify");
            
        } catch (\Exception $e) {
            Log::error('Failed to sync products from Shopify: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a product should be treated as a service
     */
    private function isServiceProduct(array $product): bool
    {
        // Add your logic here to determine if a product is a service
        // For example, check product type, tags, or other criteria
        $tags = $product['tags'] ?? [];
        
        // Example: Products tagged with 'service' are treated as services
        return in_array('service', $tags) || 
               in_array('motorcycle-service', $tags) ||
               in_array('booking', $tags);
    }

    /**
     * Extract duration from product (in minutes)
     */
    private function extractDurationFromProduct(array $product): int
    {
        // Try to extract duration from product description, metafields, or tags
        $description = $product['description'] ?? '';
        
        // Look for duration patterns like "60 minutes", "1 hour", etc.
        if (preg_match('/(\d+)\s*(?:minutes?|mins?)/i', $description, $matches)) {
            return (int) $matches[1];
        }
        
        if (preg_match('/(\d+)\s*hours?/i', $description, $matches)) {
            return (int) $matches[1] * 60;
        }
        
        // Default duration
        return 60;
    }

    /**
     * Extract price from product (in cents)
     */
    private function extractPriceFromProduct(array $product): int
    {
        // Get price from the first variant
        if (isset($product['variants']['edges'][0]['node']['price'])) {
            $price = $product['variants']['edges'][0]['node']['price'];
            return (int) round((float) $price * 100); // Convert to cents
        }
        
        return 0;
    }

    /**
     * Get the main variant GID for the service
     */
    private function getMainVariantGid(array $product): ?string
    {
        // Return the first variant as the main service variant
        if (isset($product['variants']['edges'][0]['node']['id'])) {
            return $product['variants']['edges'][0]['node']['id'];
        }
        
        return null;
    }
}
