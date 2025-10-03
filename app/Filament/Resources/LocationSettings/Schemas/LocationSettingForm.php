<?php

namespace App\Filament\Resources\LocationSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->relationship('location', 'name')
                    ->required(),
                TimePicker::make('open_time')
                    ->required(),
                TimePicker::make('close_time')
                    ->required(),
                Toggle::make('is_weekend_open')
                    ->required(),
                TimePicker::make('weekend_open_time'),
                TimePicker::make('weekend_close_time'),
                TextInput::make('capacity_per_slot')
                    ->required()
                    ->numeric(),
                TextInput::make('slot_duration_minutes')
                    ->required()
                    ->numeric()
                    ->default(60),
            ]);
    }
}
