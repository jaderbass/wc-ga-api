<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\ProductExportOrchestrator;
use App\Services\Woo\VariationSyncService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncVariationsBulkAction
 *
 * - Gleiches Modal & UX wie beim Parent-Sync (Shop, only_changed, dry_run)
 * - Aufruf über ProductExportOrchestrator (bevorzugt), Fallback: VariationSyncService
 * - Eigene handle()-Methode; action() ruft handle() auf (konsistent zu Deinem Wunsch)
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
        Select::make('shop_id')
          ->label('Shop')
          ->options(
            Shop::query()
              ->orderByDesc('is_default')
              ->orderBy('name')
              ->pluck('name', 'id')
          )
          ->default(fn() => Shop::query()->where('is_default', true)->value('id'))
          ->required(),
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
   * Führt den Varianten-Sync für alle ausgewählten Produkte aus.
   *
   * @param  Collection<int,Product>                       $records
   * @param  array{shop_id:int,only_changed?:bool,dry_run?:bool} $data
   */
  protected function handle(Collection $records, array $data): void
  {
    /** @var Shop $shop */
    $shop        = Shop::findOrFail($data['shop_id']);
    $onlyChanged = (bool)($data['only_changed'] ?? true);
    $dryRun      = (bool)($data['dry_run'] ?? false);

    /** @var ProductExportOrchestrator $orch */
    $orch = app(ProductExportOrchestrator::class);
    /** @var VariationSyncService $vsvc */
    $vsvc = app(VariationSyncService::class);

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
        // Bevorzugt über den Orchestrator (Varianten):
        // Erwartete mögliche Methodennamen (je nach Deinem Orchestrator):
        // - syncVariationsForProduct(Product $product, ?Shop $shop = null, bool $dryRun = false, bool $onlyChanged = true)
        // - syncProductVariants(...)
        // Wir probieren zuerst 'syncVariationsForProduct', dann 'syncProductVariants'.
        if (method_exists($orch, 'syncVariationsForProduct')) {
          $res = $orch->syncVariationsForProduct($product, $shop, $dryRun, $onlyChanged);
        } elseif (method_exists($orch, 'syncProductVariants')) {
          $res = $orch->syncProductVariants($product, $shop, $dryRun, $onlyChanged);
        } else {
          // Fallback: direkter Service-Aufruf (Signatur an Dein Projekt anpassen)
          // Versuche neue, optionale Parameter:
          try {
            $res = $vsvc->syncProduct($product, shop: $shop, dryRun: $dryRun, onlyChanged: $onlyChanged);
          } catch (\ArgumentCountError $e) {
            // Fallback auf alte Signatur (Product, failHard=false)
            $res = $vsvc->syncProduct($product, false);
          }
        }

        // Ergebniswerte konsolidieren
        $variants         = (int)($res['variants'] ?? 0);
        $created          = (int)($res['created'] ?? 0);
        $updated          = (int)($res['updated'] ?? 0);
        $skipped          = (int)($res['skipped'] ?? 0);

        // Wenn 'variants' nicht gesetzt ist, grob aus created/updated/skipped ableiten
        if ($variants === 0) {
          $variants = $created + $updated + $skipped;
        }

        $summary['variants'] += $variants;
        $summary['created']  += $created;
        $summary['updated']  += $updated;
        $summary['skipped']  += $skipped;
      } catch (\Throwable $e) {
        $summary['errors']++;
        Log::error('SyncVariationsBulkAction: error while syncing variants', [
          'product_id' => $product->id,
          'message'    => $e->getMessage(),
        ]);
      }
    }

    // UI-Feedback
    $title = $summary['errors'] > 0
      ? 'Woo-Variantensync abgeschlossen (mit Fehlern)'
      : 'Woo-Variantensync erfolgreich';

    $body = "Produkte: {$summary['products']} · Varianten: {$summary['variants']} · "
      . "neu: {$summary['created']} · aktualisiert: {$summary['updated']} · übersprungen: {$summary['skipped']} · "
      . "Fehler: {$summary['errors']}";

    Notification::make()
      ->title($title)
      ->body($body)
      ->{$summary['errors'] > 0 ? 'danger' : 'success'}()
      ->send();

    Log::info('SyncVariationsBulkAction summary', $summary + [
      'onlyChanged' => $onlyChanged,
      'dryRun'      => $dryRun,
      'shop_id'     => $shop->id,
    ]);
  }
}
