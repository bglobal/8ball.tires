<?php

namespace App\Http\Controllers\Api;

use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyCustomAppController
{
    protected ShopifyService $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Get frequently bought products based on order history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function frequentlyBoughtProducts(Request $request): JsonResponse
    {
        try {
            // Get parameters from request
            $months = $request->query('months', 3);
            $limit = $request->query('limit', 10); // Number of products to return
            
            // Validate months parameter
            if (!is_numeric($months) || $months < 1 || $months > 12) {
                return response()->json([
                    'error' => 'Invalid months parameter. Must be between 1 and 12.'
                ], 400);
            }
            
            // Validate limit parameter
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                return response()->json([
                    'error' => 'Invalid limit parameter. Must be between 1 and 100.'
                ], 400);
            }
            
            $products = $this->shopifyService->getFrequentlyBoughtProducts((int) $months);
            
            // Limit the number of products returned
            $limitedProducts = array_slice($products, 0, (int) $limit);
            
            return response()->json([
                'success' => true,
                'period_months' => $months,
                'limit' => (int) $limit,
                'total_products' => count($products),
                'returned_products' => count($limitedProducts),
                'products' => $limitedProducts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching frequently bought products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch frequently bought products',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
