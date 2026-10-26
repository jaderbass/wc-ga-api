<?php

namespace App\Services\Petzl;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Artikel aus dem Petzl-Händlerportal (petzldealer.com) abgleichen.
 *
 * Eingabe: JSON mit "columns" und "rows" (bereich, produkt, artikelnummer, variante,
 * neu, auslauf, uvp_netto, lager_stueck, naechster_wareneingang, kategorie).
 *
 * - vorhandene Varianten (gleiche Artikelnummer): nur Portal-Infos als Meta
 *   "petzldealer" (Lager, Wareneingang, neu/Auslauf, UVP) – sonst nichts ändern
 * - fehlende aktive Artikel:
 *   - gleiche Stammnummer (erste 6 Zeichen, z. B. E068BA) wie eine vorhandene Variante →
 *     als neue Variante an dieses Produkt
 *   - sonst neues Produkt (Petzl) mit Varianten, Listenpreis = UVP (EK rechnet der
 *     PurchasePriceCalculator mit den Hersteller-Rabatten), Kategorie aus der Datei
 * - fehlende Auslaufartikel werden nicht angelegt
 */
class PetzlDealerImport
{
    /** @var array<string, int> */
    public array $counts = ['vorhanden' => 0, 'neue Variante' => 0, 'neues Produkt' => 0, 'Auslauf übersprungen' => 0];

    /** @var list<string> */
    public array $created = [];

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['columns'], $data['rows'])) {
            throw new RuntimeException('Petzl-Händlerdaten: "columns" und "rows" erwartet.');
        }

        return array_map(fn (array $row): array => array_combine($data['columns'], $row), $data['rows']);
    }

    public static function key(?string $sku): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $sku));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function run(array $rows, bool $dryRun, ?int $authorId = null, string $readAt = ''): void
    {
        $manufacturerId = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', ['petzl'])->value('id')
            ?? throw new RuntimeException('Hersteller "Petzl" nicht gefunden.');

        $variations = ProductVariation::query()
            ->whereIn('product_id', Product::query()->where('manufacturer_id', $manufacturerId)->select('id'))
            ->get(['id', 'product_id', 'sku']);

        $bySku = [];
        $byBase = [];

        foreach ($variations as $variation) {
            $key = self::key($variation->sku);

            if ($key === '') {
                continue;
            }

            $bySku[$key] = $variation;
            $byBase[substr($key, 0, 6)] ??= (int) $variation->product_id;
        }

        $newProducts = [];

        foreach ($rows as $row) {
            $key = self::key($row['artikelnummer'] ?? '');

            if ($key === '') {
                continue;
            }

            if (isset($bySku[$key])) {
                $this->counts['vorhanden']++;

                if (! $dryRun) {
                    $this->storeDealerMeta($bySku[$key]->product_id, $bySku[$key]->id, $row, $readAt);
                }

                continue;
            }

            if (! empty($row['auslauf'])) {
                $this->counts['Auslauf übersprungen']++;

                continue;
            }

            $productId = $byBase[substr($key, 0, 6)] ?? null;

            if ($productId !== null) {
                $this->counts['neue Variante']++;
                $this->created[] = "+ Variante {$row['artikelnummer']} an Produkt {$productId} ({$row['produkt']})";

                if (! $dryRun) {
                    $variation = $this->createVariation($productId, $row);
                    $this->storeDealerMeta($productId, $variation->id, $row, $readAt);
                    $bySku[$key] = $variation;
                }

                continue;
            }

            $newProducts[self::normalizeName((string) $row['produkt'])][] = $row;
        }

        foreach ($newProducts as $productRows) {
            $this->counts['neues Produkt']++;
            $first = $productRows[0];
            $this->created[] = '* Produkt '.$first['produkt'].' ('.implode(', ', array_column($productRows, 'artikelnummer')).') → '.$first['kategorie'];

            if ($dryRun) {
                continue;
            }

            $product = $this->createProduct($manufacturerId, $first, $authorId);

            foreach ($productRows as $row) {
                $variation = $this->createVariation($product->id, $row);
                $this->storeDealerMeta($product->id, $variation->id, $row, $readAt);
            }
        }
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', str_replace(['®', '™'], '', $name))));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createProduct(int $manufacturerId, array $row, ?int $authorId): Product
    {
        $name = trim((string) $row['produkt']);
        $slug = Str::slug('petzl '.str_replace(['®', '™'], '', $name));
        $base = $slug;

        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $product = new Product;
        $product->forceFill([
            'manufacturer_id' => $manufacturerId,
            'product_name' => $name,
            'original_product_name' => $name,
            'slug' => $slug,
            'product_type' => 'variable',
            'source_category' => 'PetzlDealer: '.$row['bereich'],
            'author_id' => $authorId,
        ])->save();

        $categoryId = Category::query()->where('name', (string) $row['kategorie'])->orderBy('id')->value('id');

        if ($categoryId !== null) {
            $product->categories()->syncWithoutDetaching([$categoryId => ['assignment_type' => 'manual']]);
        }

        ProductMeta::query()->create([
            'product_id' => $product->id,
            'variation_id' => null,
            'scope' => 'product',
            'key' => 'petzldealer_created_at',
            'value' => now()->toDateTimeString(),
        ]);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createVariation(int $productId, array $row): ProductVariation
    {
        $variant = trim((string) ($row['variante'] ?? ''));

        $variation = new ProductVariation;
        $variation->forceFill([
            'product_id' => $productId,
            'sku' => trim((string) $row['artikelnummer']),
            'list_price_cents' => isset($row['uvp_netto']) ? (int) round((float) $row['uvp_netto'] * 100) : null,
            'attributes_json' => $variant !== '' ? ['Attribute Group: Farbe' => $variant] : null,
        ])->save();

        return $variation;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function storeDealerMeta(int $productId, int $variationId, array $row, string $readAt): void
    {
        ProductMeta::query()->updateOrCreate(
            ['product_id' => $productId, 'variation_id' => $variationId, 'scope' => 'variation', 'key' => 'petzldealer'],
            ['value' => json_encode([
                'stock' => $row['lager_stueck'] ?? null,
                'next_delivery' => $row['naechster_wareneingang'] ?? null,
                'new' => (bool) ($row['neu'] ?? false),
                'closeout' => (bool) ($row['auslauf'] ?? false),
                'uvp' => $row['uvp_netto'] ?? null,
                'area' => $row['bereich'] ?? null,
                'read_at' => $readAt,
            ], JSON_UNESCAPED_UNICODE)],
        );
    }
}
