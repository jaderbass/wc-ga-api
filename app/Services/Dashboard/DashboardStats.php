<?php

namespace App\Services\Dashboard;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\WooSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kennzahlen für das Dashboard. Alles nur lesend, je Kennzahl einige Minuten zwischengespeichert.
 */
class DashboardStats
{
    public const CACHE_SECONDS = 600;

    /** Produktmeta-Schlüssel für ausgelaufene, aber noch im Umlauf befindliche Artikel */
    public const DISCONTINUED_KEYS = ['aliens_discontinued', 'petzl_discontinued'];

    /**
     * Große Zahlen mit Verlauf der letzten 30 Tage (kumulativ).
     *
     * @return array<string, array{value: int, added: int, trend: list<int>}>
     */
    public function totals(): array
    {
        return $this->remember('totals', fn (): array => [
            'products' => $this->withTrend('products', Product::query()->count()),
            'variations' => $this->withTrend('product_variations', ProductVariation::query()->count()),
            'images' => $this->withTrend('product_images', ProductImage::query()->count()),
            'manufacturers' => ['value' => Manufacturer::query()->has('products')->count(), 'added' => 0, 'trend' => []],
            'categories' => ['value' => count($this->productCategoryIds()), 'added' => 0, 'trend' => []],
        ]);
    }

    /**
     * Hersteller mit Produkt- und Variantenzahl, größte zuerst.
     *
     * @return list<array{id: int, name: string, logo: ?string, products: int, variations: int, with_image: int, image_share: int}>
     */
    public function manufacturers(): array
    {
        return $this->remember('manufacturers', function (): array {
            $variations = DB::table('product_variations')
                ->join('products', 'products.id', '=', 'product_variations.product_id')
                ->groupBy('products.manufacturer_id')
                ->pluck(DB::raw('COUNT(*)'), 'products.manufacturer_id');

            $withImage = DB::table('products')
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('product_images')->whereColumn('product_images.product_id', 'products.id'))
                ->groupBy('manufacturer_id')
                ->pluck(DB::raw('COUNT(*)'), 'manufacturer_id');

            return Manufacturer::query()
                ->has('products')
                ->withCount('products')
                ->get(['id', 'manufacturer', 'logo_path'])
                ->map(fn (Manufacturer $m): array => [
                    'id' => $m->id,
                    'name' => (string) $m->manufacturer,
                    'logo' => $m->logoUrl(),
                    'products' => (int) $m->products_count,
                    'variations' => (int) ($variations[$m->id] ?? 0),
                    'with_image' => (int) ($withImage[$m->id] ?? 0),
                    'image_share' => (int) round(100 * ($withImage[$m->id] ?? 0) / max(1, $m->products_count)),
                ])
                ->sortByDesc('products')
                ->values()
                ->all();
        });
    }

    /**
     * Anteil der Produkte mit Bild, Beschreibung, Kategorie, Einkaufspreis, Einsatzgebiet.
     *
     * @return list<array{label: string, count: int, total: int, percent: int}>
     */
    public function quality(): array
    {
        return $this->remember('quality', function (): array {
            $total = Product::query()->count();

            $rows = [
                'Titelbild' => Product::query()->has('images')->count(),
                'Beschreibung' => Product::query()->whereNotNull('description')->where('description', '!=', '')->count(),
                'Kategorie' => $this->productsIn($this->productCategoryIds()),
                'Einkaufspreis' => Product::query()
                    ->where(fn ($q) => $q->whereNotNull('purchase_price_cents')
                        ->orWhereHas('variations', fn ($v) => $v->whereNotNull('purchase_price_cents')))
                    ->count(),
                'Einsatzgebiet' => $this->productsIn(array_merge(...array_values($this->applicationAreaIds()) ?: [[]])),
            ];

            return array_map(fn (string $label, int $count): array => [
                'label' => $label,
                'count' => $count,
                'total' => $total,
                'percent' => (int) floor(100 * $count / max(1, $total)),
            ], array_keys($rows), $rows);
        });
    }

    /**
     * Produkte je Einsatzgebiet (inkl. Unterkategorien).
     *
     * @return array<string, int>
     */
    public function applicationAreas(): array
    {
        return $this->remember('areas', function (): array {
            $counts = [];

            foreach ($this->applicationAreaIds() as $name => $ids) {
                $counts[$name] = $this->productsIn($ids);
            }

            arsort($counts);

            return array_filter($counts);
        });
    }

    /**
     * Shop-Abdeckung: im Shop (letzte Momentaufnahme), ausgelaufen (noch im Umlauf), nur Datenbank.
     *
     * @return array{shop: int, discontinued: int, database: int, snapshot_at: ?string}
     */
    public function shopCoverage(): array
    {
        return $this->remember('shop', function (): array {
            $snapshot = WooSnapshot::latestCompleted();
            $inShop = $snapshot
                ? DB::table('woo_snapshot_items')->where('woo_snapshot_id', $snapshot->id)->whereNotNull('matched_product_id')->distinct()->pluck('matched_product_id')->flip()
                : collect();

            $discontinued = DB::table('product_meta')->whereIn('key', self::DISCONTINUED_KEYS)->distinct()->pluck('product_id')
                ->reject(fn ($id) => isset($inShop[$id]))->count();

            $total = Product::query()->count();
            $shop = $inShop->count();

            return [
                'shop' => $shop,
                'discontinued' => $discontinued,
                'database' => max(0, $total - $shop - $discontinued),
                'snapshot_at' => $snapshot?->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Produktbestand am Monatsende, letzte 12 Monate.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function growth(): array
    {
        return $this->remember('growth', function (): array {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
            $before = Product::query()->where('created_at', '<', $start)->count();

            $perMonth = Product::query()
                ->where('created_at', '>=', $start)
                ->get(['created_at'])
                ->countBy(fn (Product $p) => $p->created_at?->format('Y-m'));

            $labels = [];
            $values = [];
            $running = $before;

            for ($i = 0; $i < 12; $i++) {
                $month = $start->addMonths($i);
                $running += (int) ($perMonth[$month->format('Y-m')] ?? 0);
                $labels[] = $month->locale('de')->isoFormat('MMM YY');
                $values[] = $running;
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /**
     * Verfügbarkeit: online verkaufbar / offline; Petzl-Händlerportal: Lagerstand je Variante.
     *
     * @return array{sellable: int, offline: int, petzl_in_stock: int, petzl_out: int, petzl_read_at: ?string}
     */
    public function availability(): array
    {
        return $this->remember('availability', function (): array {
            $petzl = DB::table('product_meta')->where('key', 'petzldealer')->pluck('value')
                ->map(fn ($v) => json_decode((string) $v, true) ?: []);

            return [
                'sellable' => Product::query()->where(fn ($q) => $q->whereNull('online_sellable')->orWhere('online_sellable', true))->count(),
                'offline' => Product::query()->where('online_sellable', false)->count(),
                'petzl_in_stock' => $petzl->filter(fn ($d) => (int) ($d['stock'] ?? 0) > 0)->count(),
                'petzl_out' => $petzl->filter(fn ($d) => (int) ($d['stock'] ?? 0) <= 0)->count(),
                'petzl_read_at' => $petzl->pluck('read_at')->filter()->sort()->last(),
            ];
        });
    }

    /**
     * Datenquellen mit letztem Stand. status: ok (aktuell), old (überfällig), manual (manuell gepflegt)
     *
     * @return list<array{name: string, detail: string, at: ?string, status: string}>
     */
    public function sources(): array
    {
        return $this->remember('sources', function (): array {
            $directory = (string) config('aliens_b2b.directory', 'imports/aliens-b2b');
            $file = fn (string $name): ?int => Storage::disk('local')->exists("{$directory}/{$name}-aktuell.xlsx")
                ? Storage::disk('local')->lastModified("{$directory}/{$name}-aktuell.xlsx")
                : null;

            $daily = fn (?int $ts): array => [
                'at' => $ts ? date('d.m.Y H:i', $ts) : null,
                'status' => $ts && $ts > time() - 26 * 3600 ? 'ok' : 'old',
            ];

            $snapshot = WooSnapshot::latestCompleted();
            $petzlMedia = Product::query()->max('petzl_description_fetched_at');
            $petzlDealer = DB::table('product_meta')->where('key', 'petzldealer_created_at')->max('value');

            return [
                ['name' => 'Aliens – Produktdaten', 'detail' => 'automatisch, täglich 02:30'] + $daily($file('produkte')),
                ['name' => 'Aliens – Lagerbestand & Preise', 'detail' => 'automatisch, täglich 10:30'] + $daily($file('lagerbestand')),
                ['name' => 'Petzl – Händlerportal & petzl.com', 'detail' => 'Bilder, Beschreibungen, Lagerstand', 'at' => $this->date(max((string) $petzlMedia, (string) $petzlDealer)), 'status' => 'ok'],
                ['name' => 'Skylotec – Datenfeed', 'detail' => 'Kategorien, Listenpreise, Einsatzgebiete', 'at' => null, 'status' => 'manual'],
                ['name' => 'Kask – Preisliste (PDF)', 'detail' => 'Anfrage Excel/CSV läuft', 'at' => null, 'status' => 'manual'],
                ['name' => 'Shop-Abgleich (nur lesend)', 'detail' => 'shop.psalpin.com', 'at' => $snapshot?->created_at?->format('d.m.Y H:i'), 'status' => $snapshot ? 'ok' : 'old'],
            ];
        });
    }

    /**
     * Zuletzt angelegte Produkte mit Titelbild.
     *
     * @return Collection<int, Product>
     */
    public function recentWithImages(int $limit = 12): Collection
    {
        return Product::query()
            ->has('images')
            ->with(['manufacturer:id,manufacturer', 'images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('id')])
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'product_name', 'manufacturer_id', 'created_at']);
    }

    public static function flush(): void
    {
        foreach (['totals', 'manufacturers', 'quality', 'areas', 'shop', 'growth', 'availability', 'sources'] as $key) {
            Cache::forget("dashboard:{$key}");
        }
    }

    /**
     * IDs aller Kategorien unter "Produkte" (ohne "Allgemein"/"SALE").
     *
     * @return list<int>
     */
    public function productCategoryIds(): array
    {
        $tree = $this->tree();
        $root = $tree->first(fn ($c) => $c->name === 'Produkte' && $c->parent_id !== null && $tree->get($c->parent_id)?->name === 'Produkte Übersicht')
            ?? $tree->first(fn ($c) => $c->name === 'Produkte');

        return $root ? array_values(array_filter(
            $this->descendants($tree, (int) $root->id),
            fn (int $id) => ! in_array(mb_strtolower((string) $tree->get($id)?->name), ['allgemein', 'sale'], true),
        )) : [];
    }

    /**
     * Einsatzgebiet => [Kategorie-IDs inkl. Unterkategorien]
     *
     * @return array<string, list<int>>
     */
    public function applicationAreaIds(): array
    {
        $tree = $this->tree();
        $root = $tree->first(fn ($c) => $c->name === 'Einsatzgebiet');
        $areas = [];

        if ($root) {
            foreach ($tree->where('parent_id', $root->id) as $area) {
                $areas[(string) $area->name] = [(int) $area->id, ...$this->descendants($tree, (int) $area->id)];
            }
        }

        return $areas;
    }

    /**
     * @param  list<int>  $categoryIds
     */
    protected function productsIn(array $categoryIds): int
    {
        return $categoryIds === [] ? 0 : (int) DB::table('category_product')->whereIn('category_id', $categoryIds)->distinct()->count('product_id');
    }

    /**
     * @return Collection<int, object{id: int, name: string, parent_id: ?int}>
     */
    protected function tree(): Collection
    {
        return Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
    }

    /**
     * @return list<int>
     */
    protected function descendants(Collection $tree, int $id): array
    {
        $ids = [];

        foreach ($tree->where('parent_id', $id) as $child) {
            $ids[] = (int) $child->id;
            array_push($ids, ...$this->descendants($tree, (int) $child->id));
        }

        return $ids;
    }

    /**
     * @return array{value: int, added: int, trend: list<int>}
     */
    protected function withTrend(string $table, int $total): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays(29);

        $perDay = DB::table($table)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->countBy(fn ($row) => substr((string) $row->created_at, 0, 10));

        $added = (int) $perDay->sum();
        $running = $total - $added;
        $trend = [];

        for ($i = 0; $i < 30; $i++) {
            $running += (int) ($perDay[$start->addDays($i)->toDateString()] ?? 0);
            $trend[] = $running;
        }

        return ['value' => $total, 'added' => $added, 'trend' => $trend];
    }

    protected function date(string $value): ?string
    {
        return $value !== '' && strtotime($value) ? date('d.m.Y', strtotime($value)) : null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function remember(string $key, callable $callback): mixed
    {
        return Cache::remember("dashboard:{$key}", self::CACHE_SECONDS, $callback);
    }
}
