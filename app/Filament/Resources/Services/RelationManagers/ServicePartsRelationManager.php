<?php

namespace App\Filament\Resources\Services\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicePartsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceParts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('shopify_variant_gid')
                    ->label('Shopify Variant GID')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The Shopify Product Variant GID'),
                TextInput::make('product_title')
                    ->label('Product Title')
                    ->maxLength(255)
                    ->helperText('The product title from Shopify'),
                TextInput::make('qty_per_service')
                    ->label('Quantity Per Service')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->helperText('How many of this part are needed per service'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_title')
            ->columns([
                TextColumn::make('product_title')
                    ->label('Product Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('shopify_variant_gid')
                    ->label('Variant GID')
                    ->searchable()
                    ->copyable()
                    ->limit(50),
                TextColumn::make('qty_per_service')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shopify_admin_link')
                    ->label('Shopify Admin')
                    ->formatStateUsing(fn ($record) => 'Edit on Shopify Admin')
                    ->url(fn ($record) => $this->getShopifyProductUrl($record))
                    ->openUrlInNewTab()
                    ->color('info')
                    ->visible(fn ($record) => !empty($record->shopify_product_id)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function getShopifyProductUrl($record): string
    {
        if (empty($record->shopify_product_id)) {
            return '#';
        }

        // Extract the numeric ID from the GID (e.g., "gid://shopify/Product/123456" -> "123456")
        $productId = str_replace('gid://shopify/Product/', '', $record->shopify_product_id);
        
        // Get the shop domain from config
        $shopDomain = config('shopify.shop');
        
        return "https://{$shopDomain}/admin/products/{$productId}";
    }
}
