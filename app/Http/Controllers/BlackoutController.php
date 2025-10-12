<?php

namespace App\Http\Controllers;

use App\Models\Blackout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlackoutController extends Controller
{
    /**
     * Get all blackout dates for a given location
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Validate the ID parameter
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|exists:locations,id'
            ]);

            // Fetch all blackout dates for the given location
            $blackoutDates = Blackout::where('location_id', $id)
                ->orderBy('date')
                ->pluck('date')
                ->map(function ($date) {
                    return $date->format('Y-m-d');
                })
                ->toArray();

            return response()->json($blackoutDates);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blackout dates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
