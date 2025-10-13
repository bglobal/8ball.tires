<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Services\ShopifyService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('shopify_sync')
                ->label('Shopify Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action('syncFromShopify')
                ->requiresConfirmation()
                ->modalHeading('Sync Services from Shopify')
                ->modalDescription('This will fetch products with the "services" tag from Shopify and create/update services in your system.')
                ->modalSubmitActionLabel('Sync Now'),
            CreateAction::make(),
        ];
    }

    public function syncFromShopify(): void
    {
        try {
            $shopifyService = app(ShopifyService::class);
            
            // Get services (products tagged with "services")
            $serviceProducts = $shopifyService->getProductsByTag('services');
            
            // Get all products with variants for association
            $allProducts = $shopifyService->getAllProductsWithVariants();

            $syncedCount = 0;
            $associatedPartsCount = 0;
            $errors = [];

            // Group all products by productType for easy lookup
            $productsByType = [];
            foreach ($allProducts as $productEdge) {
                $product = $productEdge['node'];
                $productType = $product['productType'] ?? 'Unknown';
                
                if (!isset($productsByType[$productType])) {
                    $productsByType[$productType] = [];
                }
                
                $productsByType[$productType][] = $product;
            }

            foreach ($serviceProducts as $productEdge) {
                $product = $productEdge['node'];
                
                try {
                    // Get the first variant for pricing
                    $variant = $product['variants']['edges'][0]['node'] ?? null;
                    $price = $variant ? (float) $variant['price'] : 0;

                    // Get the first variant for the service variant GID
                    $firstVariant = $product['variants']['edges'][0]['node'] ?? null;
                    $serviceVariantGid = $firstVariant ? $firstVariant['id'] : null;

                    // Create or update service
                    $service = \App\Models\Service::updateOrCreate(
                        [
                            'shop_id' => 1, // Assuming single shop for now
                            'title' => $product['title'],
                        ],
                        [
                            'slug' => $product['handle'],
                            'type' => $product['productType'] ?? null,
                            'price' => $price,
                            'duration_minutes' => 60, // Default duration
                            'active' => true,
                            'shopify_product_id' => $product['id'],
                            'shopify_variant_gid' => $serviceVariantGid,
                        ]
                    );

                    $syncedCount++;

                    // Associate parts based on matching product type
                    $serviceType = $product['productType'] ?? null;
                    if ($serviceType && isset($productsByType[$serviceType])) {
                        $associatedParts = $this->associatePartsToService($service, $productsByType[$serviceType]);
                        $associatedPartsCount += $associatedParts;
                    }

                    // Clean up any existing service parts that are actually services
                    $this->cleanupServiceParts($service);

                } catch (\Exception $e) {
                    $errors[] = "Failed to sync product '{$product['title']}': " . $e->getMessage();
                    Log::error('Failed to sync product from Shopify', [
                        'product' => $product,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Successfully synced {$syncedCount} services from Shopify.";
            if ($associatedPartsCount > 0) {
                $message .= " Associated {$associatedPartsCount} parts to services.";
            }

            if ($syncedCount > 0) {
                Notification::make()
                    ->title('Sync Successful')
                    ->body($message)
                    ->success()
                    ->send();
            }

            if (!empty($errors)) {
                Notification::make()
                    ->title('Sync Completed with Errors')
                    ->body('Some products failed to sync. Check the logs for details.')
                    ->warning()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Shopify sync failed', [
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->title('Sync Failed')
                ->body('Failed to sync services from Shopify. Please check the logs.')
                ->danger()
                ->send();
        }
    }

    private function associatePartsToService(\App\Models\Service $service, array $products): int
    {
        $associatedCount = 0;

        foreach ($products as $product) {
            // Skip products tagged with "services" - these are services, not parts
            $tags = $product['tags'] ?? [];
            if (in_array('services', $tags)) {
                continue;
            }

            // Skip the service product itself (additional safety check)
            if ($product['id'] === $service->slug) {
                continue;
            }

            $variants = $product['variants']['edges'] ?? [];
            
            foreach ($variants as $variantEdge) {
                $variant = $variantEdge['node'];
                
                try {
                    // Create or update service part
                    \App\Models\ServicePart::updateOrCreate(
                        [
                            'service_id' => $service->id,
                            'shopify_variant_gid' => $variant['id'],
                        ],
                        [
                            'shopify_product_id' => $product['id'],
                            'product_title' => $product['title'],
                            'qty_per_service' => 1, // Default quantity
                        ]
                    );

                    $associatedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to associate part to service', [
                        'service_id' => $service->id,
                        'variant_gid' => $variant['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $associatedCount;
    }

    private function cleanupServiceParts(\App\Models\Service $service): void
    {
        try {
            // Get all service parts for this service
            $serviceParts = $service->serviceParts;
            
            foreach ($serviceParts as $servicePart) {
                // Check if this part is actually a service by looking at the product title
                // If the product title matches the service title, it's likely a service, not a part
                if ($servicePart->product_title === $service->title) {
                    Log::info('Removing service part that is actually a service', [
                        'service_id' => $service->id,
                        'service_title' => $service->title,
                        'part_title' => $servicePart->product_title,
                        'part_id' => $servicePart->id
                    ]);
                    
                    $servicePart->delete();
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup service parts', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
