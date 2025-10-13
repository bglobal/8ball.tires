<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('shop_id')
                    ->default(1), // Default to shop_id 1, will be updated based on service
                Select::make('location_id')
                    ->relationship('location', 'name')
                    ->required(),
                Select::make('service_id')
                    ->relationship('service', 'title')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            // Get the shop_id from the selected service
                            $service = \App\Models\Service::find($state);
                            if ($service) {
                                $set('shop_id', $service->shop_id);
                            }
                        }
                    }),
                TextInput::make('customer_name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                DateTimePicker::make('slot_start_utc')
                    ->required(),
                DateTimePicker::make('slot_end_utc')
                    ->required(),
                TextInput::make('seats')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('meta')
                    ->label('Notes')
                    ->helperText('Enter additional booking information as key-value pairs. Format: key: value, key2: value2')
                    ->placeholder('special_instructions: Customer prefers morning service, vehicle_type: Sport bike, priority: high')
                    ->rows(4)
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '';
                        }
                        if (is_array($state)) {
                            $pairs = [];
                            foreach ($state as $key => $value) {
                                $pairs[] = $key . ': ' . $value;
                            }
                            return implode(', ', $pairs);
                        }
                        return $state;
                    })
                    ->dehydrateStateUsing(function ($state) {
                        if (empty($state)) {
                            return null;
                        }
                        // Convert simple format to array
                        if (str_contains($state, ':') && !str_starts_with($state, '{')) {
                            $pairs = explode(',', $state);
                            $result = [];
                            foreach ($pairs as $pair) {
                                $pair = trim($pair);
                                if (str_contains($pair, ':')) {
                                    [$key, $val] = explode(':', $pair, 2);
                                    $result[trim($key)] = trim($val);
                                }
                            }
                            return $result;
                        }
                        return $state;
                    })
                    ->columnSpanFull(),
            ]);
    }
}
