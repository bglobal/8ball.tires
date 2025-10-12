<?php

namespace App\Filament\Resources\LocationSettings\Pages;

use App\Filament\Resources\LocationSettings\LocationSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocationSetting extends EditRecord
{
    protected static string $resource = LocationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
