<?php

namespace App\Filament\Resources\LocationSettings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')
                    ->searchable(),
                TextColumn::make('open_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('close_time')
                    ->time()
                    ->sortable(),
                IconColumn::make('is_weekend_open')
                    ->boolean(),
                TextColumn::make('weekend_open_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('weekend_close_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('capacity_per_slot')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('slot_duration_minutes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
