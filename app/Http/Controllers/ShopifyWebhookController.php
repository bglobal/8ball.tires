<?php

namespace App\Http\Controllers;

use App\Jobs\RecomputeAvailability;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Handle inventory level update webhook
     */
    public function handleInventoryUpdate(Request $request)
    {
        try {
            // Log the webhook payload
            $this->logWebhook('inventory_levels/update', $request);

            // Extract location and variant IDs from payload
            $payload = $request->json()->all();
            $locationId = $payload['location_id'] ?? null;
            $variantId = $payload['inventory_item_id'] ?? null;

            if ($locationId && $variantId) {
                // Dispatch job to recompute availability
                RecomputeAvailability::dispatch($locationId, $variantId);
                
                Log::info('Inventory update webhook processed', [
                    'location_id' => $locationId,
                    'variant_id' => $variantId
                ]);
            } else {
                Log::warning('Inventory update webhook missing required data', [
                    'payload' => $payload
                ]);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Error processing inventory update webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->json()->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle product update webhook
     */
    public function handleProductUpdate(Request $request)
    {
        try {
            // Log the webhook payload
            $this->logWebhook('products/update', $request);

            // Extract product ID from payload
            $payload = $request->json()->all();
            $productId = $payload['id'] ?? null;

            if ($productId) {
                // For product updates, we might want to refresh all variants
                // This is a simplified approach - you might want to be more specific
                Log::info('Product update webhook processed', [
                    'product_id' => $productId
                ]);

                // You could dispatch a job here to refresh product data
                // ProductUpdateJob::dispatch($productId);
            } else {
                Log::warning('Product update webhook missing product ID', [
                    'payload' => $payload
                ]);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Error processing product update webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->json()->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify Shopify HMAC signature
     */
    public function verifyShopifyHmac(Request $request): bool
    {
        $rawBody = $request->getContent();
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $apiSecret = config('shopify.api_secret');

        if (!$hmacHeader || !$apiSecret) {
            return false;
        }

        $computedHmac = base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true));

        return hash_equals($computedHmac, $hmacHeader);
    }

    /**
     * Log webhook payload to database
     */
    private function logWebhook(string $type, Request $request): void
    {
        try {
            WebhookLog::create([
                'type' => $type,
                'payload_json' => $request->json()->all(),
                'processed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
}
