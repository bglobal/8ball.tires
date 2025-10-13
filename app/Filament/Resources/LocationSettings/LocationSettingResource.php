<?php

namespace App\Filament\Resources\LocationSettings;

use App\Filament\Resources\LocationSettings\Pages\CreateLocationSetting;
use App\Filament\Resources\LocationSettings\Pages\EditLocationSetting;
use App\Filament\Resources\LocationSettings\Pages\ListLocationSettings;
use App\Filament\Resources\LocationSettings\Schemas\LocationSettingForm;
use App\Filament\Resources\LocationSettings\Tables\LocationSettingsTable;
use App\Models\LocationSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LocationSettingResource extends Resource
{
    protected static ?string $model = LocationSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LocationSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocationSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocationSettings::route('/'),
            'create' => CreateLocationSetting::route('/create'),
            'edit' => EditLocationSetting::route('/{record}/edit'),
        ];
    }
}
