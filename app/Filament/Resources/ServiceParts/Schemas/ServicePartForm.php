<?php

namespace App\Filament\Resources\ServiceParts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServicePartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_id')
                    ->relationship('service', 'title')
                    ->required(),
                TextInput::make('shopify_variant_gid')
                    ->label('Shopify Variant GID')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The Shopify Product Variant GID'),
                TextInput::make('qty_per_service')
                    ->label('Quantity Per Service')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->helperText('How many of this part are needed per service'),
            ]);
    }
}
