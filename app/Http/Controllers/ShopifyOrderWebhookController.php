<?php

namespace App\Http\Controllers;

use App\Models\DraftOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyOrderWebhookController extends Controller
{
    /**
     * Handle Shopify order creation webhook
     * This is called when a draft order is completed and becomes an order
     */
    public function handleOrderCreate(Request $request): JsonResponse
    {
        try {
            $orderData = $request->all();
            $orderId = $orderData['id'] ?? null;
            
            if (!$orderId) {
                Log::warning('Shopify order webhook received without order ID', [
                    'payload' => $orderData
                ]);
                return response()->json(['error' => 'Order ID required'], 400);
            }

            // Find the draft order by looking for the order ID in custom attributes
            $draftOrder = $this->findDraftOrderByOrderId($orderData);
            
            if ($draftOrder) {
                // Update the draft order with the completed order ID
                $draftOrder->update([
                    'order_id' => $orderId,
                    'status' => 'completed',
                    'payload' => array_merge($draftOrder->payload ?? [], [
                        'completed_order' => $orderData
                    ])
                ]);

                Log::info('Draft order updated with completed order ID', [
                    'draft_order_id' => $draftOrder->draft_order_id,
                    'order_id' => $orderId
                ]);
            } else {
                Log::warning('No draft order found for completed order', [
                    'order_id' => $orderId,
                    'order_data' => $orderData
                ]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to process Shopify order webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Find draft order by looking for booking ID in order custom attributes
     */
    private function findDraftOrderByOrderId(array $orderData): ?DraftOrder
    {
        // Look for booking_id in line items custom attributes
        $lineItems = $orderData['line_items'] ?? [];
        
        foreach ($lineItems as $lineItem) {
            $customAttributes = $lineItem['properties'] ?? [];
            
            foreach ($customAttributes as $attribute) {
                if ($attribute['name'] === 'booking_id') {
                    $bookingId = $attribute['value'];
                    
                    // Find draft order by booking ID
                    return DraftOrder::whereHas('booking', function ($query) use ($bookingId) {
                        $query->where('id', $bookingId);
                    })->first();
                }
            }
        }

        return null;
    }
}
