<?php

namespace App\Filament\Resources\Blackouts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BlackoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->relationship('location', 'name')
                    ->required(),
                DatePicker::make('date')
                    ->required(),
                Textarea::make('reason')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
