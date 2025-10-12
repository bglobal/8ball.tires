<?php

namespace App\Filament\Resources\Blackouts\Pages;

use App\Filament\Resources\Blackouts\BlackoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlackouts extends ListRecords
{
    protected static string $resource = BlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
