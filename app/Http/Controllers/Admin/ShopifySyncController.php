<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ShopSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifySyncController extends Controller
{
    /**
     * Trigger Shopify sync
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            // Dispatch the ShopSync job
            ShopSync::dispatch();
            
            return response()->json([
                'success' => true,
                'message' => 'Shopify sync started successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start Shopify sync: ' . $e->getMessage()
            ], 500);
        }
    }
}
