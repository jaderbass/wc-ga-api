<?php

namespace App\Services\Export;

use App\Support\Woo\WritePolicy;
use App\Support\Woo\ManufacturerMaps\EdelridMap;
// use App\Support\Woo\ManufacturerMaps\PetzlMap;
use League\Csv\Writer;

class WooCommerceExporter
{
  public function __construct(
    protected WritePolicy $policy
  ) {}

  /**
   * Exportiert eine kleine Auswahl von Produkten (mit/ohne Varianten) in Woo-CSV-Format.
   * @param iterable $products Eloquent Collection oder Array von Arrays mit ['manufacturer'=>..., 'product'=>..., 'variations'=>[...]]
   * @return string Pfad zur erzeugten CSV
   */
  public function export(iterable $products, string $outPath): string
  {
    $csv = Writer::createFromPath($outPath, 'w+');
    $header = $this->header();
    $csv->insertOne($header);

    foreach ($products as $row) {
      $manufacturer = $row['manufacturer'] ?? 'generic';
      $product      = $row['product'] ?? [];
      $variations   = $row['variations'] ?? [];

      $productPayload = $this->mapProduct($manufacturer, $product);
      $productPayload = $this->policy->filterPayload($productPayload, $manufacturer);
      $csv->insertOne($this->toRow($header, $productPayload));

      foreach ($variations as $v) {
        $varPayload = $this->mapVariation($manufacturer, $v);
        $varPayload = $this->policy->filterPayload($varPayload, $manufacturer);
        $csv->insertOne($this->toRow($header, $varPayload + ['parent_sku' => $product['product_number'] ?? null]));
      }
    }

    return $outPath;
  }

  protected function header(): array
  {
    // Minimal-Header für Testimport; du kannst jederzeit Felder ergänzen
    return [
      'type',            // simple/product-variation
      'name',
      'slug',
      'sku',
      'parent_sku',
      'description',
      'short_description',
      'stock_status',
      'manage_stock',
      'stock_quantity',
      'length',
      'width',
      'height',
      'weight',
      // Attributspalten (variabel) – wir reservieren mal fünf Slots
      'attribute_1_name',
      'attribute_1_value',
      'attribute_1_visible',
      'attribute_1_variation',
      'attribute_2_name',
      'attribute_2_value',
      'attribute_2_visible',
      'attribute_2_variation',
      'attribute_3_name',
      'attribute_3_value',
      'attribute_3_visible',
      'attribute_3_variation',
      'attribute_4_name',
      'attribute_4_value',
      'attribute_4_visible',
      'attribute_4_variation',
      'attribute_5_name',
      'attribute_5_value',
      'attribute_5_visible',
      'attribute_5_variation',
    ];
  }

  protected function toRow(array $header, array $payload): array
  {
    $row = [];
    foreach ($header as $col) {
      $row[] = $payload[$col] ?? ($col === 'type' ? (isset($payload['parent_sku']) ? 'variation' : 'simple') : '');
    }
    return $row;
  }

  protected function mapProduct(string $manufacturer, array $product): array
  {
    return match (strtolower($manufacturer)) {
      'edelrid' => \App\Support\Woo\ManufacturerMaps\EdelridMap::product($product),
      // 'petzl'   => \App\Support\Woo\ManufacturerMaps\PetzlMap::product($product),
      default   => \App\Support\Woo\ManufacturerMaps\EdelridMap::product($product), // fallback
    };
  }

  protected function mapVariation(string $manufacturer, array $variation): array
  {
    return match (strtolower($manufacturer)) {
      'edelrid' => \App\Support\Woo\ManufacturerMaps\EdelridMap::variation($variation),
      // 'petzl'   => \App\Support\Woo\ManufacturerMaps\PetzlMap::variation($variation),
      default   => \App\Support\Woo\ManufacturerMaps\EdelridMap::variation($variation),
    };
  }
}
