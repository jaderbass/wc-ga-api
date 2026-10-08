<?php

namespace App\Services\PriceLists;

use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use App\Services\Categories\ManufacturerCategoryResolver;
use App\Services\Categories\ProductCategorySyncService;
use App\Services\PriceLists\Parsers\PriceListParser;
use App\Services\Pricing\PurchasePriceCalculator;
use App\Services\ShopComparison\ComparisonKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gleicht eine Preisliste mit der Datenbank ab und ergänzt die vorhandenen Produkte.
 *
 * Zuordnung je Zeile (erste eindeutige gewinnt):
 *   Varianten-SKU = Artikelnr. → Produkt-SKU/Artikelnummer = Artikelnr.
 *   → Varianten-EAN → Produkt-EAN
 *
 * Ergänzt werden (nur ohne --dry-run):
 * - Herstellerkategorie (products.source_category) + Eintrag in der
 *   Hersteller-Zuordnung, leere Einträge mit Vorschlag aus config/price_lists.php
 * - "AUSVERKAUFT" / Petzl "EOL" (alle Zeilen eines Produkts) → online_sellable = false
 * - Petzl "NEW" → Hinweis "Neu – lieferbar ab …" (Meta pricelist_<liste>_availability)
 * - "ABVERKAUF" → Kategorie "SALE" (Zuordnungsart "import", wird beim
 *   nächsten Einlesen wieder entfernt, wenn die Markierung weg ist)
 * - Listendaten (Preise, Gewicht, Zolltarif, Herkunft …) als Produkt-Meta
 * - leere EAN / leeres Variantengewicht
 * - danach Kategorien neu zuordnen
 *
 * - Listenpreis und EK (eigene Felder; EK aus der Liste oder Listenpreis
 *   abzüglich der Rabattstufen des Herstellers)
 *
 * Es werden keine Produkte angelegt oder gelöscht und keine Namen,
 * Beschreibungen oder Shop-Preise überschrieben. Kein Shop-Sync.
 */
class PriceListApplier
{
    public const ASSIGNMENT_IMPORT = 'import';

    /** @var array<string, list<array{type: string, id: int, product_id: int}>> */
    protected array $index = [];

    /** @var array<string, ?Category> */
    protected array $categoryCache = [];

    public function __construct(
        protected ProductCategorySyncService $categorySync,
    ) {}

    /**
     * @param  array<string, list<list<string|null>>>  $sheets  Blattname => Zeilen
     */
    public function apply(PriceListParser $parser, array $sheets, bool $dryRun = false): PriceListReport
    {
        $report = new PriceListReport($parser->key(), $dryRun);
        $manufacturerId = $this->manufacturerId($parser);
        $rows = $parser->parse($sheets);
        $report->rows = count($rows);

        $this->buildIndex($manufacturerId);

        /** @var array<int, list<array{row: PriceListRow, variation_id: ?int}>> $byProduct */
        $byProduct = [];

        foreach ($rows as $row) {
            $match = $this->match($row);

            if ($match['status'] === 'ambiguous') {
                $report->ambiguous[] = $row;

                continue;
            }

            if ($match['status'] === 'none') {
                $report->unmatched[] = $row;

                continue;
            }

            $report->matchedBy[$match['method']] = ($report->matchedBy[$match['method']] ?? 0) + 1;
            $byProduct[$match['product_id']][] = ['row' => $row, 'variation_id' => $match['variation_id']];
        }

        $report->products = count($byProduct);

        $products = Product::query()->whereIn('id', array_keys($byProduct))->get()->keyBy('id');

        // 1. Durchgang: Hersteller-Zuordnung (Einträge + Vorschläge, Stichwort-Regeln)
        foreach ($byProduct as $productId => $entries) {
            if (($product = $products->get($productId)) !== null) {
                $this->prepareProduct($parser, $product, collect($entries), $report, $dryRun);
            }
        }

        if ($dryRun) {
            return $report;
        }

        $this->ensureKeywordRules($parser, $manufacturerId, $report);

        // 2. Durchgang: Produkte ergänzen und Kategorien neu zuordnen
        $this->categorySync->flushRules();

        foreach ($byProduct as $productId => $entries) {
            if (($product = $products->get($productId)) !== null) {
                $this->writeProduct($parser, $product, collect($entries));
            }
        }

        return $report;
    }

    /**
     * @param  Collection<int, array{row: PriceListRow, variation_id: ?int}>  $entries
     */
    protected function prepareProduct(PriceListParser $parser, Product $product, Collection $entries, PriceListReport $report, bool $dryRun): void
    {
        [$source, $soldOut, $clearance] = $this->summarize($entries);

        if ($soldOut) {
            $report->soldOutProducts++;
        }

        if ($clearance) {
            $report->clearanceProducts++;
        }

        if ($this->availabilityNote($entries) !== null) {
            $report->newProducts++;
        }

        if ($source !== null && $product->manufacturer_id !== null) {
            $this->suggestMapping($parser, (int) $product->manufacturer_id, $source, $report, $dryRun);
        }
    }

    /**
     * Häufigste Herstellerkategorie, alle Zeilen ausverkauft?, mindestens eine im Abverkauf?
     *
     * @param  Collection<int, array{row: PriceListRow, variation_id: ?int}>  $entries
     * @return array{0: ?string, 1: bool, 2: bool}
     */
    protected function summarize(Collection $entries): array
    {
        $rows = $entries->pluck('row');
        $source = $rows->pluck('sourceCategory')->filter()->countBy()->sortDesc()->keys()->first();

        return [
            $source !== null ? (string) $source : null,
            $rows->every(fn (PriceListRow $row): bool => $row->isUnavailable()),
            $rows->contains(fn (PriceListRow $row): bool => $row->isClearance()),
        ];
    }

    /**
     * @param  Collection<int, array{row: PriceListRow, variation_id: ?int}>  $entries
     */
    protected function writeProduct(PriceListParser $parser, Product $product, Collection $entries): void
    {
        [$source, $soldOut, $clearance] = $this->summarize($entries);

        DB::transaction(function () use ($parser, $product, $entries, $source, $soldOut, $clearance): void {
            $updates = [];

            if ($source !== null && $product->source_category !== $source) {
                $updates['source_category'] = $source;
            }

            if ($soldOut && $product->online_sellable !== false) {
                $updates['online_sellable'] = false;
            }

            foreach ($entries as $entry) {
                /** @var PriceListRow $row */
                $row = $entry['row'];
                $variationId = $entry['variation_id'];

                ProductMeta::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'variation_id' => $variationId,
                        'scope' => $variationId !== null ? 'variation' : 'product',
                        'key' => 'pricelist_'.$parser->key(),
                    ],
                    ['value' => json_encode($row->toMeta() + ['applied_at' => now()->toDateTimeString()], JSON_UNESCAPED_UNICODE)],
                );

                if ($variationId !== null) {
                    $this->fillVariation($variationId, $row, $product->manufacturer);

                    continue;
                }

                if (blank($product->ean) && $row->ean !== null) {
                    $updates['ean'] = $row->ean;
                }

                $updates += $this->purchasePricing($row, $product->manufacturer);
            }

            if ($updates !== []) {
                $product->forceFill($updates)->save();
            }

            $this->syncSaleCategory($product, $clearance);
            $this->syncAvailabilityNote($parser, $product, $entries);
        });

        $this->categorySync->sync($product->fresh());
    }

    /**
     * Hinweis für neue Artikel ("NEW"), z. B. "Neu – lieferbar ab 01.03.2027".
     * Für die spätere Lieferzeit im Shop; wird entfernt, wenn die Markierung wegfällt.
     *
     * @param  Collection<int, array{row: PriceListRow, variation_id: ?int}>  $entries
     */
    protected function availabilityNote(Collection $entries): ?string
    {
        $newRows = $entries->pluck('row')->filter(fn (PriceListRow $row): bool => $row->isNew());

        if ($newRows->isEmpty()) {
            return null;
        }

        $from = $newRows->pluck('availableFrom')->filter()->sort()->first();

        return $from !== null
            ? 'Neu – lieferbar ab '.date('d.m.Y', (int) strtotime((string) $from))
            : 'Neu im Sortiment';
    }

    /**
     * @param  Collection<int, array{row: PriceListRow, variation_id: ?int}>  $entries
     */
    protected function syncAvailabilityNote(PriceListParser $parser, Product $product, Collection $entries): void
    {
        $key = 'pricelist_'.$parser->key().'_availability';
        $note = $this->availabilityNote($entries);

        $query = ProductMeta::query()
            ->where('product_id', $product->id)
            ->whereNull('variation_id')
            ->where('scope', 'product')
            ->where('key', $key);

        if ($note === null) {
            $query->delete();

            return;
        }

        ProductMeta::query()->updateOrCreate(
            ['product_id' => $product->id, 'variation_id' => null, 'scope' => 'product', 'key' => $key],
            ['value' => $note],
        );
    }

    /**
     * Listenpreis und EK aus einer Zeile:
     * - EK steht in der Liste (Aliens "HEK netto") → direkt übernehmen
     * - sonst EK = Listenpreis abzüglich der Rabattstufen des Herstellers
     * Listenpreis: Petzl "Unit Price", Aliens "UVP netto".
     *
     * @return array<string, int|string|null>
     */
    protected function purchasePricing(PriceListRow $row, ?Manufacturer $manufacturer): array
    {
        $list = $row->listPriceCents ?? $row->retailPriceCents;

        if ($row->purchasePriceCents !== null) {
            return array_filter([
                'list_price_cents' => $list,
                'purchase_price_cents' => $row->purchasePriceCents,
                'purchase_price_source' => PurchasePriceCalculator::SOURCE_PRICELIST,
            ], fn ($value) => $value !== null);
        }

        if ($list === null) {
            return [];
        }

        return [
            'list_price_cents' => $list,
            'purchase_price_cents' => PurchasePriceCalculator::calculate($list, $manufacturer?->purchase_discount_1, $manufacturer?->purchase_discount_2),
            'purchase_price_source' => PurchasePriceCalculator::SOURCE_CALCULATED,
        ];
    }

    protected function fillVariation(int $variationId, PriceListRow $row, ?Manufacturer $manufacturer): void
    {
        $variation = ProductVariation::query()->find($variationId);

        if ($variation === null) {
            return;
        }

        $fill = $this->purchasePricing($row, $manufacturer);

        if (blank($variation->ean) && $row->ean !== null) {
            $fill['ean'] = $row->ean;
        }

        if (blank($variation->weight) && $row->weightGrams !== null) {
            $fill['weight'] = $row->weightGrams;
        }

        if ($fill !== []) {
            $variation->forceFill($fill)->save();
        }
    }

    protected function syncSaleCategory(Product $product, bool $clearance): void
    {
        $sale = $this->category('SALE');

        if ($sale === null) {
            return;
        }

        $current = DB::table('category_product')
            ->where('product_id', $product->id)
            ->where('category_id', $sale->id)
            ->value('assignment_type');

        if ($clearance && $current === null) {
            $product->categories()->attach($sale->id, ['assignment_type' => self::ASSIGNMENT_IMPORT]);
        }

        if (! $clearance && $current === self::ASSIGNMENT_IMPORT) {
            $product->categories()->detach($sale->id);
        }
    }

    /**
     * Legt den Zuordnungs-Eintrag für eine Herstellerkategorie an und befüllt
     * ihn mit dem Vorschlag, solange er leer und ungeprüft ist.
     */
    protected function suggestMapping(PriceListParser $parser, int $manufacturerId, string $source, PriceListReport $report, bool $dryRun): void
    {
        $key = $manufacturerId.'|'.ManufacturerCategoryResolver::normalizeSource($source);

        if (isset($report->sources[$key])) {
            $report->sources[$key]['products']++;

            return;
        }

        $suggestion = $this->suggestionFor($parser, $source, $report);

        $rule = CategoryAssignmentRule::query()
            ->with('categories')
            ->where('manufacturer_id', $manufacturerId)
            ->whereNull('keyword')
            ->get()
            ->first(fn (CategoryAssignmentRule $rule): bool => ManufacturerCategoryResolver::normalizeSource($rule->source_category) === ManufacturerCategoryResolver::normalizeSource($source));

        $isOpen = $rule === null || (! $rule->exclude && $rule->categories->isEmpty() && ! $rule->is_reviewed);
        $status = $isOpen ? ($suggestion === null ? 'offen' : 'vorschlag') : 'vorhanden';

        $report->sources[$key] = [
            'manufacturer_id' => $manufacturerId,
            'source' => $source,
            'status' => $status,
            'result' => $isOpen ? $this->describeSuggestion($suggestion) : $this->describeRule($rule),
            'products' => 1,
        ];

        if ($dryRun) {
            return;
        }

        $rule ??= CategoryAssignmentRule::query()->create([
            'manufacturer_id' => $manufacturerId,
            'source_category' => $source,
        ]);

        if ($isOpen && $suggestion !== null) {
            $rule->forceFill([
                'exclude' => $suggestion['exclude'],
                'notes' => trim(($rule->notes ? $rule->notes."\n" : '').'Vorschlag aus Preisliste '.$parser->key()),
            ])->save();
            $rule->categories()->sync($suggestion['categories']->pluck('id')->all());
        }
    }

    /**
     * @return array{categories: Collection<int, Category>, exclude: bool}|null
     */
    protected function suggestionFor(PriceListParser $parser, string $source, PriceListReport $report): ?array
    {
        // 1. exakte Zuordnung je Herstellerkategorie (z. B. Petzl "HELMETS > Face Shields")
        foreach (config('price_lists.'.$parser->key().'.sources', []) as $configured => $categories) {
            if (ManufacturerCategoryResolver::normalizeSource($configured) === ManufacturerCategoryResolver::normalizeSource($source)) {
                return ['categories' => $this->categories($categories, $report), 'exclude' => false];
            }
        }

        // 2. Stichwörter in der Herstellerkategorie
        $text = ManufacturerCategoryResolver::normalizeText($source);

        foreach (config('price_lists.'.$parser->key().'.suggestions', []) as $suggestion) {
            if (! ManufacturerCategoryResolver::matchesKeyword($text, (string) $suggestion['keywords'])) {
                continue;
            }

            return [
                'categories' => $this->categories($suggestion['categories'] ?? [], $report),
                'exclude' => (bool) ($suggestion['exclude'] ?? false),
            ];
        }

        return null;
    }

    /**
     * Stichwort-Ausnahmen aus der Config als Regeln anlegen (falls noch nicht vorhanden).
     */
    protected function ensureKeywordRules(PriceListParser $parser, ?int $manufacturerId, PriceListReport $report): void
    {
        foreach (config('price_lists.'.$parser->key().'.keyword_rules', []) as $index => $config) {
            if ($manufacturerId === null) {
                break;
            }

            $rule = CategoryAssignmentRule::query()->firstOrCreate(
                [
                    'manufacturer_id' => $manufacturerId,
                    'source_category' => $config['source'] ?? null,
                    'keyword' => $config['keywords'],
                ],
                [
                    'sort_order' => ($index + 1) * 10,
                    'exclude' => (bool) ($config['exclude'] ?? false),
                    'notes' => 'Vorschlag aus Preisliste '.$parser->key(),
                ],
            );

            if ($rule->wasRecentlyCreated) {
                $rule->categories()->sync($this->categories($config['categories'] ?? [], $report)->pluck('id')->all());
                $report->keywordRulesCreated++;
            }
        }
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Category>
     */
    protected function categories(array $names, PriceListReport $report): Collection
    {
        return collect($names)
            ->map(function (string $name) use ($report): ?Category {
                $category = $this->category($name);

                if ($category === null) {
                    $report->missingCategories[$name] = true;
                }

                return $category;
            })
            ->filter()
            ->values();
    }

    protected function category(string $name): ?Category
    {
        if (! array_key_exists($name, $this->categoryCache)) {
            $this->categoryCache[$name] = Category::query()->where('name', $name)->first();
        }

        return $this->categoryCache[$name];
    }

    /**
     * @param  array{categories: Collection<int, Category>, exclude: bool}|null  $suggestion
     */
    protected function describeSuggestion(?array $suggestion): string
    {
        if ($suggestion === null) {
            return '—';
        }

        return $suggestion['exclude'] ? 'ausgeschlossen' : $suggestion['categories']->pluck('name')->implode(', ');
    }

    protected function describeRule(CategoryAssignmentRule $rule): string
    {
        return $rule->exclude ? 'ausgeschlossen' : ($rule->categories->pluck('name')->implode(', ') ?: '—');
    }

    /**
     * @return array{status: string, method?: string, product_id?: int, variation_id?: ?int}
     */
    protected function match(PriceListRow $row): array
    {
        $sku = ComparisonKey::sku($row->articleNumber);
        $ean = ComparisonKey::ean($row->ean);

        $steps = [
            'Varianten-SKU' => $sku !== null ? $this->index['variation_sku:'.$sku] ?? [] : [],
            'Produkt-SKU' => $sku !== null ? $this->index['product_sku:'.$sku] ?? [] : [],
            'Varianten-EAN' => $ean !== null ? $this->index['variation_ean:'.$ean] ?? [] : [],
            'Produkt-EAN' => $ean !== null ? $this->index['product_ean:'.$ean] ?? [] : [],
        ];

        foreach ($steps as $method => $candidates) {
            $unique = collect($candidates)->unique(fn (array $candidate): string => $candidate['type'].$candidate['id'])->values();

            if ($unique->count() > 1) {
                return ['status' => 'ambiguous'];
            }

            if ($unique->count() === 1) {
                $candidate = $unique->first();

                return [
                    'status' => 'matched',
                    'method' => $method,
                    'product_id' => $candidate['product_id'],
                    'variation_id' => $candidate['type'] === 'variation' ? $candidate['id'] : null,
                ];
            }
        }

        return ['status' => 'none'];
    }

    protected function buildIndex(?int $manufacturerId): void
    {
        $this->index = [];

        Product::query()
            ->when($manufacturerId !== null, fn ($query) => $query->where('manufacturer_id', $manufacturerId))
            ->select(['id', 'sku', 'product_number', 'ean'])
            ->orderBy('id')
            ->chunk(2000, function ($products): void {
                foreach ($products as $product) {
                    $target = ['type' => 'product', 'id' => (int) $product->id, 'product_id' => (int) $product->id];

                    foreach (array_unique(array_filter([ComparisonKey::sku($product->sku), ComparisonKey::sku($product->product_number)])) as $key) {
                        $this->index['product_sku:'.$key][] = $target;
                    }

                    if (($ean = ComparisonKey::ean($product->ean)) !== null) {
                        $this->index['product_ean:'.$ean][] = $target;
                    }
                }
            });

        ProductVariation::query()
            ->when($manufacturerId !== null, fn ($query) => $query->whereIn('product_id', Product::query()->select('id')->where('manufacturer_id', $manufacturerId)))
            ->whereNotNull('product_id')
            ->select(['id', 'product_id', 'sku', 'ean'])
            ->orderBy('id')
            ->chunk(2000, function ($variations): void {
                foreach ($variations as $variation) {
                    $target = ['type' => 'variation', 'id' => (int) $variation->id, 'product_id' => (int) $variation->product_id];

                    if (($sku = ComparisonKey::sku($variation->sku)) !== null) {
                        $this->index['variation_sku:'.$sku][] = $target;
                    }

                    if (($ean = ComparisonKey::ean($variation->ean)) !== null) {
                        $this->index['variation_ean:'.$ean][] = $target;
                    }
                }
            });
    }

    protected function manufacturerId(PriceListParser $parser): ?int
    {
        $name = $parser->manufacturerName();

        if ($name === null) {
            return null;
        }

        $id = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower($name)])->value('id');

        if ($id === null) {
            throw new RuntimeException("Hersteller \"{$name}\" nicht in der Datenbank gefunden.");
        }

        return (int) $id;
    }
}
