<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingsChart;
use App\Filament\Widgets\SeatsUtilizationChart;
use App\Filament\Widgets\UpcomingBookingsWidget;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            BookingsChart::class,
            SeatsUtilizationChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            UpcomingBookingsWidget::class,
        ];
    }
}
