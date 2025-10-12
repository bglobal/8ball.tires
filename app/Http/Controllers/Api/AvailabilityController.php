<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetAvailabilityRequest;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    /**
     * Get daily availability slots
     */
    public function index(GetAvailabilityRequest $request): JsonResponse
    {
        try {
            // Use current date if no date parameter provided
            $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
            
            $slots = $this->availabilityService->getDailySlots(
                $request->input('location_id'),
                $request->input('service_id'),
                $date
            );

            return response()->json([
                'success' => true,
                'data' => $slots
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
