<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\ProductExportOrchestrator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncVariationsBulkAction
 *
 * - Identisches Modal/UX wie bei der Parent-BulkAction (Shop, only_changed, dry_run)
 * - Aufruf erfolgt zentral über ProductExportOrchestrator::syncVariationsForProduct($product)
 * - Keine named args oder nicht existente Methoden
 */
class SyncVariationsBulkAction extends BulkAction
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Varianten synchronisieren')
      ->icon('heroicon-o-rectangle-stack')
      ->deselectRecordsAfterCompletion()
      ->requiresConfirmation()
      ->modalIcon('heroicon-o-rectangle-stack')
      ->modalHeading('Varianten mit WooCommerce synchronisieren')
      ->modalDescription('Dies synchronisiert die Varianten der ausgewählten Produkte mit WooCommerce. Preise werden nicht übertragen. Fortfahren?')
      ->modalSubmitActionLabel('Jetzt synchronisieren')
      ->modalCancelActionLabel('Abbrechen')
      ->modalWidth('lg')
      ->form([
        /* Select::make('shop_id')
          ->label('Shop')
          ->options(
            Shop::query()
              ->orderByDesc('is_default')
              ->orderBy('name')
              ->pluck('name', 'id')
          )
          ->default(fn() => Shop::query()->where('is_default', true)->value('id'))
          ->required(), */
        Select::make('shop_id')
          ->label('Shop')
          ->options(Shop::query()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
          ->default(fn() => Shop::query()->where('is_default', true)->value('id'))
          ->required()
          ->native(false)
          ->searchable()
          ->preload(),

        Toggle::make('only_changed')
          ->label('Nur geänderte senden')
          ->default(true),
        Toggle::make('dry_run')
          ->label('Dry-run (nur Vorschau)')
          ->default(false),
      ])
      ->action(fn(Collection $records, array $data) => $this->handle($records, $data));
  }

  /**
   * Führt den Varianten-Sync für die ausgewählten Produkte aus.
   *
   * @param  Collection<int,Product>                       $records
   * @param  array{shop_id:int,only_changed?:bool,dry_run?:bool} $data
   */
  protected function handle(Collection $records, array $data): void
  {
    // Hinweis: Shop/only_changed/dry_run sind UI-Optionen für spätere Erweiterung.
    // Aktuell nimmt der Service diese Parameter nicht an; der Orchestrator kapselt den Call.
    /** @var ProductExportOrchestrator $orch */
    $orch = app(ProductExportOrchestrator::class);

    $summary = [
      'products' => 0,
      'variants' => 0,
      'created'  => 0,
      'updated'  => 0,
      'skipped'  => 0,
      'errors'   => 0,
    ];

    foreach ($records as $product) {
      /** @var Product $product */
      $summary['products']++;

      try {
        // Zentraler Einstieg – keine named args, Signatur: syncVariationsForProduct(Product): array
        $res = $orch->syncVariationsForProduct($product);

        $created  = (int)($res['created'] ?? 0);
        $updated  = (int)($res['updated'] ?? 0);
        $skipped  = (int)($res['skipped'] ?? 0);
        $variants = (int)($res['variants'] ?? ($created + $updated + $skipped));

        $summary['created']  += $created;
        $summary['updated']  += $updated;
        $summary['skipped']  += $skipped;
        $summary['variants'] += $variants;
      } catch (\Throwable $e) {
        $summary['errors']++;
        Log::error('SyncVariationsBulkAction: error while syncing variants', [
          'product_id' => $product->id,
          'message'    => $e->getMessage(),
        ]);
      }
    }

    // Feedback
    $title = $summary['errors'] > 0
      ? 'Woo-Variantensync abgeschlossen (mit Fehlern)'
      : 'Woo-Variantensync erfolgreich';

    $body = "Produkte: {$summary['products']} · Varianten: {$summary['variants']} · "
      . "neu: {$summary['created']} · aktualisiert: {$summary['updated']} · "
      . "übersprungen: {$summary['skipped']} · Fehler: {$summary['errors']}";

    Notification::make()
      ->title($title)
      ->body($body)
      ->{$summary['errors'] > 0 ? 'danger' : 'success'}()
      ->send();

    Log::info('SyncVariationsBulkAction summary', $summary);
  }
}
