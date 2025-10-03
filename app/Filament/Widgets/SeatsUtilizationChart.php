<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Location;
use Filament\Widgets\ChartWidget;

class SeatsUtilizationChart extends ChartWidget
{
    protected ?string $heading = 'Seats Utilization Per Location';

    protected function getData(): array
    {
        $locations = Location::with('bookings')->get();
        
        $data = [];
        $labels = [];
        
        foreach ($locations as $location) {
            $totalSeats = $location->bookings()
                ->where('status', 'confirmed')
                ->sum('seats');
            $data[] = $totalSeats;
            $labels[] = $location->name;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Seats Booked',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
