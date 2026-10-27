<?php

namespace App\Services\Edelrid;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use App\Services\Categories\ProductCategorySyncService;
use App\Services\PriceLists\Parsers\EdelridPriceListParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Edelrid-Sortiment mit einer neuen Preisliste abgleichen (z. B. "PL_2027_WORK SAFETY").
 *
 * Artikelnummer: 12 Ziffern, die ersten 5 = Modell (83272 = Static Low Stretch 10,5 mm).
 *
 * - Bereich: nur Produkte, deren Herstellerkategorie eine Überschrift der Liste ist
 *   oder deren Modell in der Liste steht. Alles andere (z. B. Sport) bleibt unberührt.
 * - Artikel fehlt in der Liste → Variante bekommt Meta "edelrid_discontinued";
 *   fehlen alle Artikel eines Produkts → Produkt ausgelaufen + offline (online_sellable = false).
 *   Nichts wird gelöscht. Taucht ein Artikel wieder auf, wird der alte Zustand wiederhergestellt.
 * - Artikel neu in der Liste:
 *   - Modell gibt es schon → neue Variante an diesem Produkt
 *   - sonst neues Produkt (je Modell) mit Varianten, Listenpreis netto, Herstellerkategorie
 *   - Verdacht auf geänderte Artikelnummer (gleicher Name wie ein komplett ausgelaufenes
 *     Produkt) → weder anlegen noch das alte Produkt auslaufen lassen, nur melden
 * Listenpreise/EK vorhandener Artikel übernimmt pricelist:apply edelrid.
 */
class EdelridCatalogSync
{
    public const META = 'edelrid_discontinued';

    /** @var array<string, int> */
    public array $counts = [
        'in der Liste' => 0,
        'vorhanden' => 0,
        'Variante ausgelaufen' => 0,
        'Produkt ausgelaufen' => 0,
        'wieder aktiv' => 0,
        'neue Variante' => 0,
        'neues Produkt' => 0,
        'Verdacht neue Nummer' => 0,
        'außerhalb des Bereichs' => 0,
        'ohne Edelrid-Nummer' => 0,
    ];

    /** @var array<string, list<string>> */
    public array $lines = ['neu' => [], 'ausgelaufen' => [], 'verdacht' => []];

    /**
     * true: Nachfolger mit neuer Artikelnummer als neues Produkt anlegen, das alte
     * läuft aus; beide werden verknüpft (Meta edelrid_successor / edelrid_predecessor).
     */
    public function __construct(public bool $successorsAsNew = false) {}

    protected function linkSuccessor(Product $old, Product $new): void
    {
        ProductMeta::query()->updateOrCreate(
            ['product_id' => $old->id, 'variation_id' => null, 'scope' => 'product', 'key' => 'edelrid_successor'],
            ['value' => (string) $new->id],
        );
        ProductMeta::query()->updateOrCreate(
            ['product_id' => $new->id, 'variation_id' => null, 'scope' => 'product', 'key' => 'edelrid_predecessor'],
            ['value' => (string) $old->id],
        );
    }

    /**
     * Artikelzeilen aus der Tabelle (Überschrift, Nummer, Name, Farbe, Länge/Größe, Ausführung, Preis, Einheit).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<array{heading: string, sku: string, name: string, color: string, size: string, spec: string, list_price_cents: ?int, unit: ?string}>
     */
    public static function rows(array $rows): array
    {
        $heading = null;
        $result = [];

        foreach ($rows as $row) {
            $filled = array_values(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));

            if (count($filled) === 1 && str_contains((string) $filled[0], '|')) {
                $heading = trim(explode('|', (string) $filled[0])[0]);

                continue;
            }

            $sku = trim((string) ($row[0] ?? ''));

            if ($heading === null || preg_match('/^\d{12}$/', $sku) !== 1 || trim((string) ($row[1] ?? '')) === '') {
                continue;
            }

            $result[] = [
                'heading' => $heading,
                'sku' => $sku,
                'name' => trim((string) preg_replace('/\s+/', ' ', (string) $row[1])),
                'color' => trim((string) ($row[2] ?? '')),
                'size' => trim((string) ($row[3] ?? '')),
                'spec' => trim((string) ($row[4] ?? '')),
                'list_price_cents' => EdelridPriceListParser::cents($row[6] ?? null),
                'unit' => trim((string) ($row[7] ?? '')) ?: null,
            ];
        }

        return $result;
    }

    /**
     * Name für den Vergleich: "Static Low Stretch 10,5mm II" → "static low stretch 10.5mm"
     */
    public static function key(string $name): string
    {
        $name = mb_strtolower(str_replace(['®', '™', 'EDELRID', 'Edelrid'], '', $name));
        $name = str_replace(',', '.', $name);
        $name = (string) preg_replace('/\s+ii$/u', '', trim($name));

        return (string) preg_replace('/\s+/u', '', $name);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  aus {@see rows()}
     */
    public function run(array $rows, bool $dryRun, ?int $authorId = null): void
    {
        $manufacturerId = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', ['edelrid'])->value('id')
            ?? throw new RuntimeException('Hersteller "Edelrid" nicht gefunden.');

        $list = collect($rows)->keyBy('sku');
        $headings = $list->pluck('heading')->unique()->map(fn ($h) => mb_strtolower($h))->all();
        $models = $list->keys()->map(fn ($sku) => substr($sku, 0, 5))->unique()->flip();
        $this->counts['in der Liste'] = $list->count();

        $products = Product::query()
            ->where('manufacturer_id', $manufacturerId)
            ->with(['variations:id,product_id,sku', 'meta' => fn ($q) => $q->where('key', self::META)])
            ->get(['id', 'sku', 'product_number', 'product_name', 'original_product_name', 'source_category', 'online_sellable']);

        // Artikelnummer → [Produkt, Variante|null]; Modell → Produkt
        $known = [];
        $modelProduct = [];
        $inScope = [];

        foreach ($products as $product) {
            $skus = $this->skusOf($product);
            $scoped = in_array(mb_strtolower((string) $product->source_category), $headings, true)
                || collect($skus)->contains(fn ($sku) => isset($models[substr($sku, 0, 5)]));

            if (! $scoped) {
                $this->counts['außerhalb des Bereichs']++;

                continue;
            }

            $inScope[$product->id] = $product;

            foreach ($product->variations as $variation) {
                if ($key = $this->digits($variation->sku)) {
                    $known[$key] = [$product, $variation];
                    $modelProduct[substr($key, 0, 5)] ??= $product;
                }
            }

            foreach ([$product->sku, $product->product_number] as $sku) {
                if (($key = $this->digits($sku)) && ! isset($known[$key])) {
                    $known[$key] = [$product, null];
                    $modelProduct[substr($key, 0, 5)] ??= $product;
                }
            }
        }

        // 1. Ausgelaufene Produkte (alle Nummern fehlen) – für den Namensvergleich mit neuen Modellen
        $goneProducts = collect($inScope)->filter(fn (Product $p) => $this->skusOf($p) !== []
            && collect($this->skusOf($p))->every(fn ($sku) => ! $list->has($sku)));
        $goneByName = $goneProducts->groupBy(fn (Product $p) => self::key((string) ($p->original_product_name ?: $p->product_name)));

        // 2. Neue Artikel: an vorhandenes Modell hängen oder je Modell neues Produkt
        $newByModel = [];

        foreach ($list as $sku => $row) {
            if (isset($known[$sku])) {
                $this->counts['vorhanden']++;

                continue;
            }

            $model = substr($sku, 0, 5);

            if (isset($modelProduct[$model])) {
                $this->counts['neue Variante']++;
                $this->lines['neu'][] = "+ Variante {$sku} an Produkt {$modelProduct[$model]->id} ({$row['name']} {$row['color']} {$row['size']})";

                if (! $dryRun) {
                    $this->createVariation($modelProduct[$model], $row);
                }

                continue;
            }

            $newByModel[$model][] = $row;
        }

        $suspectProductIds = [];

        foreach ($newByModel as $model => $modelRows) {
            $name = self::displayName($modelRows);
            $gone = $goneByName->get(self::key($name));

            if ($gone !== null) {
                $this->counts['Verdacht neue Nummer']++;
                $this->lines['verdacht'][] = "? {$name}: neu ".implode(', ', array_column($modelRows, 'sku'))
                    .' – bisher Produkt '.$gone->pluck('id')->implode(', ').' ('.implode(', ', $this->skusOf($gone->first())).')'
                    .($this->successorsAsNew ? ' → neues Produkt, altes ausgelaufen' : '');

                if (! $this->successorsAsNew) {
                    $suspectProductIds = [...$suspectProductIds, ...$gone->pluck('id')->all()];

                    continue;
                }
            }

            $this->counts['neues Produkt']++;
            $this->lines['neu'][] = "* Produkt {$name} ({$modelRows[0]['heading']}): ".implode(', ', array_column($modelRows, 'sku'));

            if (! $dryRun) {
                $product = $this->createProduct($manufacturerId, $name, $modelRows, $authorId);

                foreach ($gone ?? [] as $predecessor) {
                    $this->linkSuccessor($predecessor, $product);
                }
            }
        }

        // 3. Ausgelaufen / wieder aktiv
        foreach ($inScope as $product) {
            if (in_array($product->id, $suspectProductIds, true)) {
                continue;
            }

            $this->syncStatus($product, $list, $dryRun);
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $list
     */
    protected function syncStatus(Product $product, Collection $list, bool $dryRun): void
    {
        $skus = $this->skusOf($product);

        if ($skus === []) {
            $this->counts['ohne Edelrid-Nummer']++;

            return;
        }

        foreach ($product->variations as $variation) {
            $key = $this->digits($variation->sku);
            $meta = $product->meta->first(fn ($m) => (int) $m->variation_id === (int) $variation->id);

            if ($key === null) {
                continue;
            }

            if (! $list->has($key) && $meta === null) {
                $this->counts['Variante ausgelaufen']++;

                if (! $dryRun) {
                    ProductMeta::query()->create(['product_id' => $product->id, 'variation_id' => $variation->id, 'scope' => 'variation', 'key' => self::META, 'value' => json_encode(['since' => now()->toDateString()])]);
                }
            } elseif ($list->has($key) && $meta !== null && ! $dryRun) {
                $meta->delete();
            }
        }

        $productMeta = $product->meta->first(fn ($m) => $m->variation_id === null);
        $allGone = collect($skus)->every(fn ($sku) => ! $list->has($sku));

        if ($allGone && $productMeta === null) {
            $this->counts['Produkt ausgelaufen']++;
            $this->lines['ausgelaufen'][] = "- {$product->id} {$product->product_name} (".implode(', ', array_slice($skus, 0, 4)).(count($skus) > 4 ? ' …' : '').')';

            if (! $dryRun) {
                ProductMeta::query()->create(['product_id' => $product->id, 'variation_id' => null, 'scope' => 'product', 'key' => self::META, 'value' => json_encode(['since' => now()->toDateString(), 'online_sellable' => $product->online_sellable])]);
                $product->forceFill(['online_sellable' => false])->saveQuietly();
            }
        } elseif (! $allGone && $productMeta !== null) {
            $this->counts['wieder aktiv']++;

            if (! $dryRun) {
                $previous = json_decode((string) $productMeta->value, true)['online_sellable'] ?? null;
                $product->forceFill(['online_sellable' => $previous])->saveQuietly();
                $productMeta->delete();
            }
        }
    }

    /**
     * Häufigster Name der Zeilen ohne " II"-Zusatz.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function displayName(array $rows): string
    {
        $name = collect($rows)->pluck('name')->countBy()->sortDesc()->keys()->first();

        return trim((string) preg_replace('/\s+II$/u', '', (string) $name));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function createProduct(int $manufacturerId, string $name, array $rows, ?int $authorId): Product
    {
        $slug = $base = Str::slug('edelrid '.$name);

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
            'source_category' => $rows[0]['heading'],
            'author_id' => $authorId,
        ])->save();

        ProductMeta::query()->create(['product_id' => $product->id, 'variation_id' => null, 'scope' => 'product', 'key' => 'edelrid_created_at', 'value' => now()->toDateTimeString()]);

        foreach ($rows as $row) {
            $this->createVariation($product, $row);
        }

        app(ProductCategorySyncService::class)->sync($product);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createVariation(Product $product, array $row): ProductVariation
    {
        $attributes = array_filter([
            'Attribute Group: Farbe' => $row['color'] ?: null,
            self::isLength($row['size']) ? 'Attribute Group: Länge' : 'Attribute Group: Größe' => self::isEmptySize($row['size']) ? null : $row['size'],
            'Attribute Group: Ausführung' => $row['spec'] ?: null,
        ]);

        $variation = new ProductVariation;
        $variation->forceFill([
            'product_id' => $product->id,
            'sku' => $row['sku'],
            'list_price_cents' => $row['list_price_cents'],
            'attributes_json' => $attributes ?: null,
        ])->save();

        if ($row['unit'] !== null && $row['unit'] !== 'PC') {
            ProductMeta::query()->create(['product_id' => $product->id, 'variation_id' => $variation->id, 'scope' => 'variation', 'key' => 'edelrid_price_unit', 'value' => $row['unit']]);
        }

        return $variation;
    }

    public static function isLength(string $size): bool
    {
        return preg_match('/^\d+([.,]\d+)?\s*M$/i', trim($size)) === 1;
    }

    public static function isEmptySize(string $size): bool
    {
        return trim($size) === '' || preg_match('/^0+$/', trim($size)) === 1;
    }

    /**
     * @return list<string>
     */
    protected function skusOf(Product $product): array
    {
        $skus = $product->variations->map(fn ($v) => $this->digits($v->sku))->filter()->values()->all();

        if ($skus === []) {
            $skus = array_values(array_filter([$this->digits($product->sku), $this->digits($product->product_number)]));
        }

        return array_values(array_unique($skus));
    }

    protected function digits(mixed $sku): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $sku) ?? '';

        return strlen($digits) === 12 ? $digits : null;
    }
}
