<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WooResetMappingsCommand extends Command
{
  /**
   * Setzt Woo-Mappings (woo_product_id) zurück.
   *
   * Optionen:
   *  --all        → Alle woo_product_id auf NULL setzen (brutal)
   *  --validate   → Jede woo_product_id via Woo-API prüfen; 404/invalid → NULL
   *  --chunk=200  → Batch-Größe beim Validieren (Default: 200)
   *
   * Ohne Option werden nur Zahlen/Statistiken gezeigt (keine Änderungen).
   */
  protected $signature = 'woo:mappings:reset
        {--all : Setzt ALLE woo_product_id auf NULL}
        {--validate : Prüft jede ID bei Woo; ungültige werden auf NULL gesetzt}
        {--chunk=200 : Batch-Größe beim Validieren}';

  protected $description = 'Bereinigt lokale Woo-Mappings (woo_product_id) – komplett oder validierungsbasiert';

  public function handle(): int
  {
    $doAll      = (bool) $this->option('all');
    $doValidate = (bool) $this->option('validate');
    $chunkSize  = (int)  $this->option('chunk');

    if ($doAll && $doValidate) {
      $this->error('Bitte entweder --all ODER --validate verwenden, nicht beides.');
      return self::INVALID;
    }

    $totalWithMapping = Product::whereNotNull('woo_product_id')->count();
    $this->info("Produkte mit gesetzter woo_product_id: {$totalWithMapping}");

    if (!$doAll && !$doValidate) {
      $this->line('Keine Option gesetzt. Nimm --all für Hard-Reset oder --validate für gezieltes Bereinigen.');
      return self::SUCCESS;
    }

    // Shop / Client vorbereiten (für --validate)
    $woo = null;
    if ($doValidate) {
      /** @var \App\Models\Shop $shop */
      $shop = Shop::query()->first();
      if (!$shop) {
        $this->error('Kein Shop-Datensatz in der DB gefunden.');
        return self::INVALID;
      }
      $woo = new WooClient($shop);
    }

    if ($doAll) {
      $affected = DB::table('products')->whereNotNull('woo_product_id')->update(['woo_product_id' => null]);
      $this->warn("ALLES genullt. Betroffene Datensätze: {$affected}");
      return self::SUCCESS;
    }

    // --validate Pfad
    $this->info("Starte Validierung in Batches zu {$chunkSize} …");
    $bar = $this->output->createProgressBar($totalWithMapping);
    $bar->start();

    $nulledIds = [];
    $keptIds   = [];
    $errors    = 0;

    Product::whereNotNull('woo_product_id')
      ->orderBy('id')
      ->chunkById($chunkSize, function ($batch) use ($woo, $bar, &$nulledIds, &$keptIds, &$errors) {
        /** @var Product $p */
        foreach ($batch as $p) {
          $remoteId = (int) $p->woo_product_id;
          $isValid  = $this->checkWooId($woo, $remoteId);

          if ($isValid === true) {
            $keptIds[] = $p->id;
          } elseif ($isValid === false) {
            // Ungültig → NULL setzen
            DB::table('products')->where('id', $p->id)->update(['woo_product_id' => null]);
            $nulledIds[] = $p->id;
          } else {
            // null = „unentschieden“/Fehler → zählen, nicht ändern
            $errors++;
          }

          $bar->advance();
        }
      });

    $bar->finish();
    $this->newLine(2);

    $this->info("Validierung fertig.");
    $this->line("  ✓ behalten:  " . count($keptIds));
    $this->line("  ␡ genullt:   " . count($nulledIds));
    $this->line("  ! Fehler:    " . $errors);

    // Kleine Zusammenfassung ausgeben (IDs nur bei kleiner Menge anzeigen)
    if (count($nulledIds) && count($nulledIds) <= 20) {
      $this->warn('Genullte Produkt-IDs: ' . implode(', ', $nulledIds));
    }

    return self::SUCCESS;
  }

  /**
   * Prüft, ob eine Woo-Produkt-ID remote existiert.
   *
   * @return bool|null  true = existiert; false = existiert NICHT; null = unklar/Fehler (z.B. transienter DNS)
   */
  protected function checkWooId(WooClient $woo, int $remoteId): ?bool
  {
    try {
      $res = $woo->get("products/{$remoteId}");
      // gültige Antwort muss id haben und matchen
      if (is_array($res) && isset($res['id']) && (int) $res['id'] === $remoteId) {
        return true;
      }
      // Wenn Response kein passender Datensatz → als ungültig behandeln
      return false;
    } catch (\RuntimeException $e) {
      // WooClient wrappt Exceptions; Code/Message heuristisch auswerten
      $code = (int) $e->getCode();
      $msg  = $e->getMessage();

      // 404 / invalid_id → sicher ungültig
      if (
        $code === 404 ||
        str_contains($msg, 'woocommerce_rest_product_invalid_id') ||
        str_contains($msg, 'product_invalid_id')
      ) {
        return false;
      }

      // DNS-Hiccups etc. → unklar (nicht ändern)
      if (str_contains($msg, 'cURL error 6') || str_contains($msg, 'Could not resolve host')) {
        return null;
      }

      // Sonstige Client/Serverfehler: konservativ unklar
      return null;
    } catch (\Throwable $e) {
      // Irgendetwas anderes: unklar
      return null;
    }
  }
}
