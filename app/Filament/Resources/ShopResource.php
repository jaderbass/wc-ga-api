<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages;
use App\Models\Shop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

/**
 * Class ShopResource
 *
 * Filament-Resource zur Verwaltung von WooCommerce Shops (API-Zugänge & Optionen).
 */
class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Woo Sync';

    protected static ?string $navigationLabel = 'Shops';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Allgemein')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        TextInput::make('base_url')
                            ->label('Base URL')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com'),
                        TextInput::make('api_version')
                            ->label('API-Version')
                            ->default(config('woo.default_api_version', 'wc/v3'))
                            ->maxLength(20),
                    ])->columns(3),

                Section::make('Authentifizierung')
                    ->schema([
                        TextInput::make('consumer_key')
                            ->label('Consumer Key')
                            ->password()
                            ->nullable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Leer lassen, um den bestehenden Key nicht zu überschreiben.'),
                        TextInput::make('consumer_secret')
                            ->label('Consumer Secret')
                            ->password()
                            ->nullable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Leer lassen, um das bestehende Secret nicht zu überschreiben.'),
                        TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->nullable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Wird zur HMAC-Prüfung eingehender Webhooks verwendet.'),
                    ])->columns(3),

                Section::make('Optionen')
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Als Standard-Shop verwenden')
                            ->inline(false),
                        KeyValue::make('rate_limit_json')
                            ->label('Rate Limits (optional)')
                            ->keyLabel('Key')
                            ->valueLabel('Wert')
                            ->helperText('z. B. rpm = 100, burst = 40. Wenn leer, werden Werte aus config/woo.php verwendet.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_url')
                    ->label('Base URL')
                    ->wrap()
                    ->limit(50),
                TextColumn::make('api_version')
                    ->label('API')
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->since(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
