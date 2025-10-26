<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EightballProxyController extends Controller
{
    private const LATE_POINT_API_KEY = 'lp_n1k6BVf3h7JRyjXkWMSoXi0BBZYRaOLL4QohDPQJrrrrraffdf';
    private string $externalApiUrl = 'http://wp-latepoint.local/index.php/wp-json/v1';

    /**
     * Get all locations (proxy for /locations)
     */
    public function getLocations(): JsonResponse
    {
        try {
            $response = Http::get($this->externalApiUrl . '/latepoint/locations');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch locations from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getLocations', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch locations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service categories by location (proxy for /services/categories/location/{id})
     */
    public function getServiceCategories(Request $request, $locationId): JsonResponse
    {
        try {
            $response = Http::get($this->externalApiUrl . "/latepoint/services/categories/location/{$locationId}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch service categories from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getServiceCategories', ['error' => $e->getMessage(), 'locationId' => $locationId]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch service categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get services by location (proxy for /services/location/{id})
     */
    public function getServicesByLocation(Request $request, $locationId): JsonResponse
    {
        try {
            $url = $this->externalApiUrl . "/latepoint/services/location/{$locationId}";

            // Add query parameters if present
            $queryParams = $request->only(['category_id', 'service_ids']);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch services from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getServicesByLocation', ['error' => $e->getMessage(), 'locationId' => $locationId]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch services',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agents by location and service (proxy for /agents/location/{id}/service/{id})
     */
    public function getAgentsByLocationAndService(Request $request, $locationId, $serviceId): JsonResponse
    {
        try {
            $response = Http::get($this->externalApiUrl . "/latepoint/agents/location/{$locationId}/service/{$serviceId}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch agents from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getAgentsByLocationAndService', [
                'error' => $e->getMessage(),
                'locationId' => $locationId,
                'serviceId' => $serviceId
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch agents',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar availability (proxy for /calendar)
     */
    public function getCalendarAvailability(Request $request): JsonResponse
    {
        try {
            $queryParams = $request->only([
                'service_id', 'location_id', 'agent_id',
                'date_from', 'date_to', 'total_attendees'
            ]);

            $url = $this->externalApiUrl . '/latepoint/calendar?' . http_build_query($queryParams);
            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch calendar availability from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getCalendarAvailability', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch calendar availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get time slots for specific date (proxy for /time-slots-auto)
     */
    public function getTimeSlots(Request $request): JsonResponse
    {
        try {
            $queryParams = $request->only([
                'service_id', 'location_id', 'agent_id',
                'date', 'total_attendees'
            ]);

            $url = $this->externalApiUrl . '/latepoint/time-slots-auto?' . http_build_query($queryParams);
            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch time slots from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getTimeSlots', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch time slots',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check availability for specific service, agent, and date (proxy for /availability)
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        try {
            $queryParams = $request->only([
                'service_id', 'agent_id', 'location_id',
                'date', 'duration'
            ]);

            $url = $this->externalApiUrl . '/latepoint/availability?' . http_build_query($queryParams);
            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to check availability from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - checkAvailability', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to check availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createBooking(Request $request): JsonResponse
    {
        try {
            $url = $this->externalApiUrl . '/latepoint/bookings';

            $allowedFields = [
                'service_id', 'agent_id', 'location_id', 'start_date', 'start_time',
                'customer', 'status', 'send_confirmation',
                'product_title', 'product_price', 'product_variant_id',
                'service_name', 'service_variant_id', 'service_price','upsells'
            ];

            // Capture only allowed fields
            $filteredData = $request->only($allowedFields);
            $filteredData['customer'] = $request->input('customer'); // ensure nested object
            $filteredData['upsells'] = $request->input('upsells'); // ensure nested object

            Log::info('Filtered Booking Payload:', $filteredData);

            $response = Http::withHeaders([
                'X-API-Key' => self::LATE_POINT_API_KEY
            ])->asJson()->post($url, $filteredData);

            Log::info('External API Response:', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to create booking via external API',
                'status' => $response->status(),
                'response' => $response->body()
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - createBooking', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to create booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific booking (proxy for /bookings/{id})
     */
    public function getBooking(Request $request, $id): JsonResponse
    {
        try {
            $response = Http::get($this->externalApiUrl . "/latepoint/bookings/{$id}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch booking from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getBooking', ['error' => $e->getMessage(), 'bookingId' => $id]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get location mappings (proxy for /wp-json/v1/location-mappings)
     */
    public function getLocationMappings(): JsonResponse
    {
        try {
            // This endpoint uses a different base URL (l/v1 instead of latepoint/v1)
            $response = Http::get($this->externalApiUrl.'/location-mappings');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch location mappings from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getLocationMappings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch location mappings',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Shopify services by product type (proxy for /wp-json/v1/shopify-services)
     */
    public function getShopifyServicesByProductType(Request $request): JsonResponse
    {
        try {

            $url = $this->externalApiUrl.'/shopify-services';

            // Add query parameters if present
            $queryParams = $request->only(['product_type']);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch services from external API'
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Proxy error - getShopifyServicesByProductType', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch services',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
