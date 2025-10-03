<?php

namespace App\Filament\Resources\Blackouts;

use App\Filament\Resources\Blackouts\Pages\CreateBlackout;
use App\Filament\Resources\Blackouts\Pages\EditBlackout;
use App\Filament\Resources\Blackouts\Pages\ListBlackouts;
use App\Filament\Resources\Blackouts\Schemas\BlackoutForm;
use App\Filament\Resources\Blackouts\Tables\BlackoutsTable;
use App\Models\Blackout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlackoutResource extends Resource
{
    protected static ?string $model = Blackout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BlackoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlackoutsTable::configure($table);
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
            'index' => ListBlackouts::route('/'),
            'create' => CreateBlackout::route('/create'),
            'edit' => EditBlackout::route('/{record}/edit'),
        ];
    }
}
