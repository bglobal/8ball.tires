<?php

namespace App\Filament\Resources\Blackouts\Pages;

use App\Filament\Resources\Blackouts\BlackoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlackout extends EditRecord
{
    protected static string $resource = BlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
