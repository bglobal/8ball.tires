<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CalendarPreviewController extends Controller
{
    public function index(Request $request)
    {
        $selectedLocation = $request->get('location_id');
        $selectedDate = $request->get('date', Carbon::today()->format('Y-m-d'));
        $selectedService = $request->get('service_id');
        $slots = [];

        // Load slots if location, date, and service are selected
        if ($selectedLocation && $selectedDate && $selectedService) {
            try {
                $availabilityService = app(AvailabilityService::class);
                $date = Carbon::parse($selectedDate);
                
                // Get slots for the selected service only
                $service = \App\Models\Service::where('id', $selectedService)
                    ->where('active', true)
                    ->first();
                
                if ($service) {
                    $serviceSlots = $availabilityService->getDailySlots(
                        $selectedLocation,
                        $service->id,
                        $date
                    );
                    
                    foreach ($serviceSlots as $slot) {
                        $slots[] = [
                            'service' => $service->title,
                            'service_id' => $service->id,
                            'slot_start' => $slot['slotStart'],
                            'slot_end' => $slot['slotEnd'],
                            'seats_left' => $slot['seatsLeft'],
                            'inventory_ok' => $slot['inventoryOk'],
                            'available' => $slot['seatsLeft'] > 0 && $slot['inventoryOk'],
                        ];
                    }
                    
                    // Sort by slot start time
                    usort($slots, function ($a, $b) {
                        return strtotime($a['slot_start']) - strtotime($b['slot_start']);
                    });
                }
                
            } catch (\Exception $e) {
                session()->flash('error', 'Error loading slots: ' . $e->getMessage());
            }
        }

        $locations = Location::where('is_active', true)->pluck('name', 'id');
        $services = \App\Models\Service::where('active', true)->pluck('title', 'id');
        $lastSyncTime = Cache::get('shopify_last_sync');

        return view('calendar-preview', compact(
            'locations',
            'services',
            'selectedLocation',
            'selectedDate',
            'selectedService',
            'slots',
            'lastSyncTime'
        ));
    }

    public function syncShopify()
    {
        // Dispatch the ShopSync job
        \App\Jobs\ShopSync::dispatch();
        
        return response()->json([
            'success' => true,
            'message' => 'Shopify sync started successfully'
        ]);
    }

    public function getLastSyncTime(): ?string
    {
        return Cache::get('shopify_last_sync');
    }
}
