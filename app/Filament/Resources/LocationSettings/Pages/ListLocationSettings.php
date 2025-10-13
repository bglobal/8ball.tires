<?php

namespace App\Filament\Resources\LocationSettings\Pages;

use App\Filament\Resources\LocationSettings\LocationSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocationSettings extends ListRecords
{
    protected static string $resource = LocationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
