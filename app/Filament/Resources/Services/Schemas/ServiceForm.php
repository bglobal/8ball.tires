<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('type')
                    ->label('Type')
                    ->maxLength(255),
                TextInput::make('price_cents')
                    ->label('Price')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->helperText('Enter price in dollars (e.g., 29.99)')
                    ->afterStateHydrated(function (TextInput $component, $state) {
                        $component->state($state / 100);
                    })
                    ->dehydrateStateUsing(fn ($state) => round($state * 100)),
                TextInput::make('duration_minutes')
                    ->label('Duration (minutes)')
                    ->required()
                    ->numeric(),
                Toggle::make('active')
                    ->label('Active')
                    ->required(),
            ]);
    }
}
