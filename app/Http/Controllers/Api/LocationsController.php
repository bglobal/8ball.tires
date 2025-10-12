<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    /**
     * Get all active locations
     */
    public function index(): JsonResponse
    {
        $locations = Location::where('is_active', true)
            ->select('id', 'name', 'timezone')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Get location details with settings by ID
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Validate the ID parameter
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|exists:locations,id'
            ]);

            // Find the location with its settings
            $location = Location::with('settings')
                ->where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found or inactive'
                ], 404);
            }

            // Prepare the response data
            $locationData = [
                'id' => $location->id,
                'name' => $location->name,
                'timezone' => $location->timezone,
                'is_active' => $location->is_active,
                'settings' => $location->settings ? [
                    'slot_duration_minutes' => $location->settings->slot_duration_minutes,
                    'open_time' => $location->settings->open_time?->format('H:i'),
                    'close_time' => $location->settings->close_time?->format('H:i'),
                    'is_weekend_open' => $location->settings->is_weekend_open,
                    'weekend_open_time' => $location->settings->weekend_open_time?->format('H:i'),
                    'weekend_close_time' => $location->settings->weekend_close_time?->format('H:i'),
                    'capacity_per_slot' => $location->settings->capacity_per_slot,
                ] : null
            ];

            return response()->json([
                'success' => true,
                'data' => $locationData
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
