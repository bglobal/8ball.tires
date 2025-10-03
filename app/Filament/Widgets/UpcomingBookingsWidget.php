<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class UpcomingBookingsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $upcomingBookings = Booking::with(['service', 'location'])
            ->where('slot_start_utc', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('slot_start_utc')
            ->limit(10)
            ->get();

        $stats = [];
        
        foreach ($upcomingBookings as $booking) {
            $stats[] = Stat::make(
                $booking->customer_name,
                $booking->slot_start_utc->format('M j, Y g:i A')
            )
            ->description($booking->service->title . ' - ' . $booking->location->name)
            ->descriptionIcon('heroicon-m-calendar')
            ->color($this->getStatusColor($booking->status->value));
        }

        return $stats;
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'confirmed' => 'success',
            'completed' => 'info',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
