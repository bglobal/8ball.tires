<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServicesController extends Controller
{
    /**
     * Get all active services
     */
    public function index(): JsonResponse
    {
        $services = Service::where('active', true)
            ->select('id', 'title', 'duration_minutes', 'price_cents')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price_cents / 100, // Convert cents to dollars
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }
}
