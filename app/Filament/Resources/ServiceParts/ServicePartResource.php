<?php

namespace App\Filament\Resources\ServiceParts;

use App\Filament\Resources\ServiceParts\Pages\CreateServicePart;
use App\Filament\Resources\ServiceParts\Pages\EditServicePart;
use App\Filament\Resources\ServiceParts\Pages\ListServiceParts;
use App\Filament\Resources\ServiceParts\Schemas\ServicePartForm;
use App\Filament\Resources\ServiceParts\Tables\ServicePartsTable;
use App\Models\ServicePart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServicePartResource extends Resource
{
    protected static ?string $model = ServicePart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ServicePartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicePartsTable::configure($table);
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
            'index' => ListServiceParts::route('/'),
            'create' => CreateServicePart::route('/create'),
            'edit' => EditServicePart::route('/{record}/edit'),
        ];
    }
}
