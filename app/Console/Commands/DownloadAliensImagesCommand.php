<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadAliensImagesCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * --manufacturerId: Optionaler Filter
   * --limit: Optionales Limit (für Tests)
   * --force: lädt auch neu, wenn bereits lokale Pfade vorhanden sind
   */
  protected $signature = 'aliens:images:download
    {--manufacturerId= : Filter by manufacturer_id}
    {--limit= : Limit number of products}
    {--force : Redownload even if local paths already exist}';

  /**
   * The console command description.
   */
  protected $description = 'Downloads Aliens product images from meta (aliens_image_urls) into storage/public and stores local paths in meta (aliens_image_paths).';

  public function handle(): int
  {
    $manufacturerId = $this->option('manufacturerId');
    $limit = $this->option('limit');
    $force = (bool) $this->option('force');

    $query = Product::query()
      ->when($manufacturerId, fn($q) => $q->where('manufacturer_id', (int) $manufacturerId))
      ->whereHas('meta', function ($q) {
        $q->where('scope', 'product')
          ->where('key', 'aliens_image_urls')
          ->whereNull('variation_id');
      })
      ->with(['meta' => function ($q) {
        $q->whereNull('variation_id');
      }]);

    if (is_numeric($limit) && (int) $limit > 0) {
      $query->limit((int) $limit);
    }

    $products = $query->get();

    if ($products->isEmpty()) {
      $this->info('No products found with aliens_image_urls.');
      return self::SUCCESS;
    }

    $processed = 0;
    $downloaded = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($products as $product) {
      $processed++;

      $urlsMeta = $product->meta->firstWhere('key', 'aliens_image_urls');
      if (!$urlsMeta) {
        $skipped++;
        continue;
      }

      $urls = json_decode((string) $urlsMeta->value, true);
      if (!is_array($urls) || $urls === []) {
        $skipped++;
        continue;
      }

      $pathsMeta = $product->meta->firstWhere('key', 'aliens_image_paths');
      if (!$force && $pathsMeta && is_string($pathsMeta->value) && trim($pathsMeta->value) !== '') {
        $skipped++;
        continue;
      }

      $baseDir = public_path($this->buildBaseDir($product->manufacturer_id, $product->id));
      if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
      }

      $localPaths = [];

      foreach ($urls as $url) {
        if (!is_string($url) || !preg_match('~^https?://~i', $url)) {
          continue;
        }

        try {
          $response = Http::timeout(20)
            ->retry(2, 400)
            ->withHeaders([
              'Accept' => 'image/*',
            ])
            ->get($url);

          if (!$response->successful()) {
            $failed++;
            Log::warning('Aliens image download failed (http)', [
              'product_id' => $product->id,
              'url' => $url,
              'status' => $response->status(),
            ]);
            continue;
          }

          $contentType = (string) $response->header('Content-Type');
          if ($contentType !== '' && !str_starts_with(strtolower($contentType), 'image/')) {
            $failed++;
            Log::warning('Aliens image download failed (non-image content-type)', [
              'product_id' => $product->id,
              'url' => $url,
              'content_type' => $contentType,
            ]);
            continue;
          }

          $filename = $this->makeFilenameFromUrl($url, $contentType);
          $relativeWebPath = $this->buildBaseDir($product->manufacturer_id, $product->id) . '/' . $filename;
          $absolutePath = $baseDir . '/' . $filename;

          file_put_contents($absolutePath, $response->body());

          // wir speichern jetzt den WEB-Pfad (ohne /storage), z.B. products/aliens/...
          $localPaths[] = $relativeWebPath;
          $downloaded++;
        } catch (\Throwable $e) {
          $failed++;
          Log::warning('Aliens image download failed (exception)', [
            'product_id' => $product->id,
            'url' => $url,
            'error' => $e->getMessage(),
          ]);
          continue;
        }
      }

      $localPaths = array_values(array_unique($localPaths));

      if ($localPaths !== []) {
        $product->meta()->updateOrCreate(
          ['scope' => 'product', 'key' => 'aliens_image_paths', 'variation_id' => null],
          ['value' => json_encode($localPaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
      }
    }

    $this->info("Processed: {$processed}");
    $this->info("Downloaded: {$downloaded}");
    $this->info("Skipped: {$skipped}");
    $this->info("Failed: {$failed}");

    return self::SUCCESS;
  }

  /**
   * Baut das Basisverzeichnis (public disk) für Produktbilder.
   *
   * @param  int|null  $manufacturerId
   * @param  int       $productId
   * @return string
   */
  private function buildBaseDir(?int $manufacturerId, int $productId): string
  {
    $m = $manufacturerId ?: 0;
    return "products/aliens/m{$m}/p{$productId}";
  }

  /**
   * Erstellt einen stabilen Dateinamen aus URL + Content-Type.
   *
   * @param  string  $url
   * @param  string  $contentType
   * @return string
   */
  private function makeFilenameFromUrl(string $url, string $contentType): string
  {
    $path = parse_url($url, PHP_URL_PATH);
    $base = is_string($path) ? basename($path) : '';
    $base = trim($base);

    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?? 'image';

    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if ($ext === '') {
      $ext = $this->guessExtensionFromContentType($contentType) ?: 'jpg';
      $base = rtrim($base, '.') . '.' . $ext;
    }

    // sehr kurze/komische Namen stabilisieren
    if (strlen($base) < 5) {
      $hash = substr(sha1($url), 0, 12);
      $base = "img_{$hash}.{$ext}";
    }

    return $base;
  }

  /**
   * Schätzt die Dateiendung anhand des Content-Type.
   *
   * @param  string  $contentType
   * @return string|null
   */
  private function guessExtensionFromContentType(string $contentType): ?string
  {
    $ct = strtolower(trim(explode(';', $contentType)[0] ?? ''));

    return match ($ct) {
      'image/jpeg' => 'jpg',
      'image/jpg'  => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      'image/gif'  => 'gif',
      default      => null,
    };
  }
}
