<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JudgemeReviewController extends Controller
{
    private const JUDGEME_API_TOKEN = 'Ok5bo-Dv0GgIEbHF_5Gld-5Y9V8';
    private const SHOP_DOMAIN = '8balltires.myshopify.com';
    private string $externalApiUrl = 'https://judge.me/api/v1';

    /**
     * Get all reviews
     */
    public function getReviews(Request $request): JsonResponse
    {
        try {
            $url = $this->externalApiUrl . '/reviews';
            
            // Build query parameters
            $queryParams = [
                'api_token' => self::JUDGEME_API_TOKEN,
                'shop_domain' => self::SHOP_DOMAIN,
                'per_page' => $request->input('per_page', 100)
            ];
            
            // Add optional parameters from request
            if ($request->has('product_id')) {
                $queryParams['product_id'] = $request->input('product_id');
            }
            if ($request->has('page')) {
                $queryParams['page'] = $request->input('page');
            }
            
            $url .= '?' . http_build_query($queryParams);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->get($url);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch reviews from Judge.me API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Judge.me API error - getReviews', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch reviews',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
