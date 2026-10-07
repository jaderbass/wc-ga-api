<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\WooSnapshot;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShopComparisonDbOnly extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Shop-Abgleich';

    protected static ?string $navigationLabel = 'Nur in Datenbank';

    protected static ?string $title = 'Nur in der Datenbank (nicht im Shop gefunden)';

    protected static ?string $slug = 'shop-abgleich-nur-datenbank';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.shop-comparison-db-only';

    public ?int $snapshotId = null;

    public function mount(): void
    {
        $this->snapshotId = WooSnapshot::latestCompleted()?->id;
    }

    public function table(Table $table): Table
    {
        $snapshotId = $this->snapshotId;

        return $table
            ->query(
                Product::query()
                    ->when(
                        $snapshotId,
                        fn (Builder $query) => $query->whereNotExists(fn ($sub) => $sub
                            ->selectRaw('1')
                            ->from('woo_snapshot_items')
                            ->where('woo_snapshot_items.woo_snapshot_id', $snapshotId)
                            ->whereColumn('woo_snapshot_items.matched_product_id', 'products.id')),
                        fn (Builder $query) => $query->whereRaw('1 = 0')
                    )
                    ->with('manufacturer:id,manufacturer')
                    ->withCount('variations')
            )
            ->defaultSort('product_name')
            ->columns([
                Tables\Columns\TextColumn::make('product_number')
                    ->label('Artikelnummer')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produkt')
                    ->searchable()
                    ->wrap()
                    ->sortable()
                    ->url(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('manufacturer.manufacturer')
                    ->label('Hersteller')
                    ->sortable(),

                Tables\Columns\TextColumn::make('variations_count')
                    ->label('Varianten')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->label('Hersteller')
                    ->relationship('manufacturer', 'manufacturer')
                    ->searchable()
                    ->preload(),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading($snapshotId ? 'Alle Produkte wurden im Shop gefunden' : 'Keine Shop-Daten')
            ->emptyStateDescription($snapshotId ? null : 'Auf dem Server "php artisan woo:snapshot" ausführen, um den Shop (nur lesend) einzulesen.');
    }
}
