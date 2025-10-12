<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Location;
use App\Models\Service;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_start_utc')
                    ->label('Slot Start')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('seats')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->value)
                    ->color(fn ($state): string => match ($state->value) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                    }),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable(),
                TextColumn::make('service.title')
                    ->label('Service')
                    ->searchable(),
                TextColumn::make('id')
                    ->label('Actions')
                    ->formatStateUsing(fn ($state) => '👁️ View')
                    ->url(fn ($record) => route('filament.admin.resources.bookings.view', $record))
                    ->color('primary'),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->options(Location::all()->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('service_id')
                    ->label('Service')
                    ->options(Service::all()->pluck('title', 'id'))
                    ->searchable(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
                Filter::make('slot_start_utc')
                    ->form([
                        DatePicker::make('slot_start_from')
                            ->label('From Date'),
                        DatePicker::make('slot_start_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['slot_start_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('slot_start_utc', '>=', $date),
                            )
                            ->when(
                                $data['slot_start_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('slot_start_utc', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('slot_start_utc', 'desc');
    }
}
