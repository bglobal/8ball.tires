<?php

namespace App\Filament\Resources\ServiceParts\Pages;

use App\Filament\Resources\ServiceParts\ServicePartResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceParts extends ListRecords
{
    protected static string $resource = ServicePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
