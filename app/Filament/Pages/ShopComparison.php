<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use App\Services\ShopComparison\WooSnapshotStats;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ShopComparison extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Shop-Abgleich';

    protected static ?string $navigationLabel = 'Übersicht';

    protected static ?string $title = 'Shop-Abgleich';

    protected static ?string $slug = 'shop-abgleich';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.shop-comparison';

    public ?int $snapshotId = null;

    public function mount(): void
    {
        $this->snapshotId = WooSnapshot::latestCompleted()?->id;
    }

    public function getSnapshot(): ?WooSnapshot
    {
        return $this->snapshotId ? WooSnapshot::with('shop')->find($this->snapshotId) : null;
    }

    public function getStats(): ?array
    {
        $snapshot = $this->getSnapshot();

        return $snapshot ? WooSnapshotStats::for($snapshot) : null;
    }

    public function table(Table $table): Table
    {
        $shopBase = rtrim((string) $this->getSnapshot()?->shop?->base_url, '/');

        return $table
            ->query(
                WooSnapshotItem::query()
                    ->where('woo_snapshot_id', $this->snapshotId ?? 0)
                    ->with('matchedProduct:id,product_name')
            )
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WooSnapshotItem::TYPE_LABELS[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ean')
                    ->label('EAN')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name im Shop')
                    ->searchable()
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('match_status')
                    ->label('Abgleich')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WooSnapshotItem::MATCH_LABELS[$state] ?? '—')
                    ->color(fn (?string $state) => match ($state) {
                        WooSnapshotItem::MATCH_SKU, WooSnapshotItem::MATCH_VIA_VARIATIONS => 'success',
                        WooSnapshotItem::MATCH_EAN => 'info',
                        WooSnapshotItem::MATCH_AMBIGUOUS => 'warning',
                        WooSnapshotItem::MATCH_SHOP_ONLY => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('matchedProduct.product_name')
                    ->label('Produkt in der Datenbank')
                    ->formatStateUsing(fn ($state, WooSnapshotItem $record) => '#'.$record->matched_product_id.' – '.$state)
                    ->url(fn (WooSnapshotItem $record) => $record->matched_product_id
                        ? ProductResource::getUrl('edit', ['record' => $record->matched_product_id])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('regular_price')
                    ->label('Preis (Shop)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('woo_id')
                    ->label('Im Shop')
                    ->formatStateUsing(fn () => 'öffnen')
                    ->url(fn (WooSnapshotItem $record) => $shopBase !== ''
                        ? $shopBase.'/wp-admin/post.php?post='.($record->woo_parent_id ?: $record->woo_id).'&action=edit'
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('match_status')
                    ->label('Abgleich')
                    ->options(WooSnapshotItem::MATCH_LABELS),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options(WooSnapshotItem::TYPE_LABELS),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Keine Shop-Daten')
            ->emptyStateDescription('Auf dem Server "php artisan woo:snapshot" ausführen, um den Shop (nur lesend) einzulesen.');
    }
}
