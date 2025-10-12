<?php

namespace App\Filament\Resources\ServiceParts\Pages;

use App\Filament\Resources\ServiceParts\ServicePartResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServicePart extends EditRecord
{
    protected static string $resource = ServicePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
