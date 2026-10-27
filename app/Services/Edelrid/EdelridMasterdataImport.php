<?php

namespace App\Services\Edelrid;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMeta;
use RuntimeException;

/**
 * Edelrid-Stammdaten aus dem Händlerbereich ("Stammdaten_Work_Safety_2027_DE.xlsx").
 *
 * Eine Zeile je Artikel (12-stellige Art.Nr.) mit EAN, Gewicht, Maßen, Herkunft,
 * Zolltarif, Material, Produkt-Text, USP's, technischen Informationen, Zertifizierung,
 * Links zu Gebrauchsanleitung/Konformitätserklärung und bis zu 11 Bildern (PNG, je Farbe).
 *
 * Es werden nur leere Felder gefüllt. Ausnahme Bilder: Hat ein Produkt nur die
 * WebP-Bilder von edelrid.com (edelrid:fetch-media), werden sie durch die
 * Stammdaten-Bilder ersetzt (offizielle PNG je Farbe, für den Shop-Import besser).
 */
class EdelridMasterdataImport
{
    public const WEBSITE_IMAGE_HOST = 'shopapi.edelrid.com';

    public const MAX_IMAGES = 15;

    /** @var array<string, int> */
    public array $counts = [
        'Artikel in der Datei' => 0,
        'zugeordnet' => 0,
        'nicht in der Datenbank' => 0,
        'Produkte' => 0,
        'Bilder neu' => 0,
        'Bilder ersetzt (Website → Stammdaten)' => 0,
        'Beschreibung' => 0,
        'Kurzbeschreibung' => 0,
        'Zertifizierung' => 0,
        'Gebrauchsanleitung' => 0,
        'Konformitätserklärung' => 0,
        'EAN' => 0,
        'Gewicht' => 0,
    ];

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<array<string, string>> Artikelzeilen, Spaltenname => Wert
     */
    public static function rows(array $rows): array
    {
        $header = null;
        $result = [];

        foreach ($rows as $row) {
            $cells = array_map(fn ($v) => trim((string) $v), $row);

            if ($header === null) {
                if (in_array('Art.Nr.', $cells, true) && in_array('EAN', $cells, true)) {
                    $header = $cells;
                }

                continue;
            }

            $item = [];

            foreach ($header as $i => $name) {
                if ($name !== '') {
                    $item[$name] ??= str_replace("\r", '', (string) ($row[$i] ?? ''));
                }
            }

            if (preg_match('/^\d{12}$/', trim($item['Art.Nr.'] ?? '')) === 1) {
                $result[] = $item;
            }
        }

        if ($header === null) {
            throw new RuntimeException('Kopfzeile mit "EAN" und "Art.Nr." nicht gefunden.');
        }

        return $result;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function run(array $rows, bool $dryRun): void
    {
        $manufacturerId = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', ['edelrid'])->value('id')
            ?? throw new RuntimeException('Hersteller "Edelrid" nicht gefunden.');

        $this->counts['Artikel in der Datei'] = count($rows);

        $products = Product::query()->where('manufacturer_id', $manufacturerId)->with(['variations', 'images'])->get();
        $variationBySku = [];

        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                $sku = preg_replace('/\D+/', '', (string) $variation->sku);

                if ($sku !== '') {
                    $variationBySku[$sku] = [$product, $variation];
                }
            }
        }

        $byProduct = [];

        foreach ($rows as $row) {
            $sku = trim($row['Art.Nr.']);

            if (! isset($variationBySku[$sku])) {
                $this->counts['nicht in der Datenbank']++;

                continue;
            }

            $this->counts['zugeordnet']++;
            [$product, $variation] = $variationBySku[$sku];
            $byProduct[$product->id]['product'] = $product;
            $byProduct[$product->id]['rows'][] = $row;
            $this->applyVariation($product, $variation, $row, $dryRun);
        }

        foreach ($byProduct as ['product' => $product, 'rows' => $productRows]) {
            $this->counts['Produkte']++;
            $this->applyProduct($product, $productRows, $dryRun);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function applyVariation(Product $product, $variation, array $row, bool $dryRun): void
    {
        $fill = [];

        if (blank($variation->ean) && preg_match('/^\d{8,14}$/', trim($row['EAN'] ?? ''))) {
            $fill['ean'] = trim($row['EAN']);
            $this->counts['EAN']++;
        }

        $weight = (int) round((float) str_replace(',', '.', $row['Gewicht ohne Verpackung (g)'] ?? ''));

        if (blank($variation->weight) && $weight > 0) {
            $fill['weight'] = $weight;
            $this->counts['Gewicht']++;
        }

        if ($dryRun) {
            return;
        }

        if ($fill !== []) {
            $variation->forceFill($fill)->saveQuietly();
        }

        $dimension = fn (string $key) => (float) str_replace(',', '.', $row[$key] ?? '') ?: null;

        ProductMeta::query()->updateOrCreate(
            ['product_id' => $product->id, 'variation_id' => $variation->id, 'scope' => 'variation', 'key' => 'edelrid_masterdata'],
            ['value' => json_encode(array_filter([
                'color_no' => $row['Farb.Nr.'] ?? null,
                'color' => $row['Farbtext'] ?? null,
                'size' => $row['Größe'] ?? null,
                'gender' => $row['GESCHLECHT'] ?? null,
                'material' => $row['Materialzusammensetzung'] ?? null,
                'origin' => $row['Ursprungsland'] ?? null,
                'customs_tariff' => $row['Zolltarifnummer'] ?? null,
                'packaging_unit' => $row['Verpackungseinheit'] ?? null,
                'gross_weight_g' => $dimension('Bruttogewicht'),
                'length' => $dimension('Länge'),
                'width' => $dimension('Breite'),
                'height' => $dimension('Höhe'),
                'certification' => $row['Zertifizierung'] ?? null,
                'video' => $row['Videolink'] ?? null,
                'images' => self::images([$row]),
                'source' => 'Stammdaten Work Safety 2027',
            ], fn ($v) => $v !== null && $v !== '' && $v !== []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    protected function applyProduct(Product $product, array $rows, bool $dryRun): void
    {
        $first = $rows[0];
        $changes = [];

        if (blank(strip_tags((string) $product->description)) && ($description = self::description($first)) !== null) {
            $changes['description'] = $description;
            $this->counts['Beschreibung']++;
        }

        if (blank(strip_tags((string) $product->short_description)) && ($short = EdelridWebsite::firstSentence($first['Produkt-Text'] ?? '')) !== null) {
            $changes['short_description'] = $short;
            $this->counts['Kurzbeschreibung']++;
        }

        foreach (['certification' => ['Zertifizierung', 'Zertifizierung'], 'manual_url' => ['URL Gebrauchsanleitung', 'Gebrauchsanleitung'], 'declaration_of_compliance' => ['URL Konformitätserklärung', 'Konformitätserklärung']] as $field => [$column, $label]) {
            $value = collect($rows)->pluck($column)->map(fn ($v) => trim((string) $v))->filter()->first();

            if ($value !== null && blank($product->{$field})) {
                $changes[$field] = mb_substr($value, 0, 255);
                $this->counts[$label]++;
            }
        }

        if (! $dryRun && $changes !== []) {
            $product->forceFill($changes)->save();
        }

        $this->applyImages($product, self::images($rows), $dryRun);
    }

    /**
     * @param  list<string>  $urls
     */
    protected function applyImages(Product $product, array $urls, bool $dryRun): void
    {
        if ($urls === []) {
            return;
        }

        $existing = $product->images;
        $onlyWebsite = $existing->isNotEmpty() && $existing->every(fn ($image) => str_contains((string) $image->url, self::WEBSITE_IMAGE_HOST));

        if ($existing->isNotEmpty() && ! $onlyWebsite) {
            return;
        }

        $this->counts[$onlyWebsite ? 'Bilder ersetzt (Website → Stammdaten)' : 'Bilder neu']++;

        if ($dryRun) {
            return;
        }

        if ($onlyWebsite) {
            ProductImage::query()->whereIn('id', $existing->pluck('id'))->delete();
        }

        foreach (array_slice($urls, 0, self::MAX_IMAGES) as $i => $url) {
            ProductImage::query()->firstOrCreate(['product_id' => $product->id, 'url' => $url], ['is_main' => $i === 0]);
        }
    }

    /**
     * Bildlinks aller Zeilen: je Zeile Bild 0–10, Titelbilder der Farben zuerst, ohne Doppelte.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<string>
     */
    public static function images(array $rows): array
    {
        $main = [];
        $detail = [];

        foreach ($rows as $row) {
            for ($i = 0; $i <= 10; $i++) {
                $url = trim((string) ($row["URL Produktbild {$i}"] ?? ''));

                if (preg_match('#^https?://#', $url) && strlen($url) <= 255) {
                    $i === 0 ? $main[$url] = true : $detail[$url] = true;
                }
            }
        }

        return array_keys($main + $detail);
    }

    /**
     * Produkt-Text, Vorteile (USP's), technische Informationen und Zertifizierung als HTML.
     *
     * @param  array<string, string>  $row
     */
    public static function description(array $row): ?string
    {
        $text = trim($row['Produkt-Text'] ?? '');

        if ($text === '') {
            return null;
        }

        $list = function (string $value): string {
            $items = array_filter(array_map('trim', preg_split('/\n+/', $value) ?: []));

            return $items === [] ? '' : "<ul>\n".implode("\n", array_map(fn ($i) => '<li>'.e($i).'</li>', $items))."\n</ul>";
        };

        $html = '<p>'.e($text).'</p>';

        if (($usps = $list($row["USP's"] ?? '')) !== '') {
            $html .= "\n<h4>Vorteile</h4>\n".$usps;
        }

        if (($tech = $list($row['Technische Informationen und Technologien'] ?? '')) !== '') {
            $html .= "\n<h4>Technische Informationen</h4>\n".$tech;
        }

        if (($cert = trim($row['Zertifizierung'] ?? '')) !== '') {
            $html .= "\n<p><strong>Zertifizierung:</strong> ".e($cert).'</p>';
        }

        return $html;
    }
}
