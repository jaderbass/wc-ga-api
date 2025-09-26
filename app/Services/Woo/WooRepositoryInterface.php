<?php

namespace App\Services\Woo;

/**
 * Interface WooRepositoryInterface
 *
 * Zweck:
 * - Abstraktion für alle WooCommerce-spezifischen Lookups und Mutationen,
 *   die vom WooParentResolver und ProductExportOrchestrator genutzt werden.
 * - Ermöglicht einfache Austauschbarkeit (Mocking im Test, andere Backends, etc.).
 *
 * Wichtige Hinweise:
 * - Die tatsächliche Implementierung (z. B. WooApiRepository) sollte die
 *   WooCommerce-REST-API konsumieren (Automattic\WooCommerce\Client o. ä.).
 * - Rückgabeformen sind absichtlich generisch (array), damit du deine
 *   bestehende Antwortstruktur übernehmen kannst.
 *
 * @package App\Services\Woo
 */
interface WooRepositoryInterface
{
  /**
   * Sucht ein Produkt ODER eine Variation anhand exakter SKU.
   *
   * Erwartung:
   * - Liefert ein Array mit genug Infos, damit der Resolver Typ/IDs ermitteln kann.
   *   Üblich ist die rohe API-Antwort des gefundenen Items (Product/Variation).
   *
   * @param  string $sku
   * @return array|null
   */
  public function findBySku(string $sku): ?array;

  /**
   * Sucht ein Produkt ODER eine Variation anhand exakter EAN (falls im Shop gepflegt).
   *
   * Hinweis:
   * - In vielen Woo-Shops steckt EAN in einem Custom-Field/Meta (z. B. `_ean`).
   *   Implementierung: erst Products, dann Variations durchsuchen (oder via Filter/Meta-Query, falls verfügbar).
   *
   * @param  string $ean
   * @return array|null
   */
  public function findByEan(string $ean): ?array;

  /**
   * Sucht ein Produkt ODER eine Variation anhand exakter MPN (Hersteller-Nr.).
   *
   * @param  string $mpn
   * @return array|null
   */
  public function findByMpn(string $mpn): ?array;

  /**
   * Liefert die interne ID eines Items (Product/Variation).
   *
   * @param  array $item  Rohe API-Antwort eines Products oder einer Variation.
   * @return int
   */
  public function getItemId(array $item): int;

  /**
   * Liefert den Typ des Items.
   *
   * Erwartete Werte:
   * - 'parent'    → variables Produkt (Parent)
   * - 'simple'    → einfaches Produkt
   * - 'variation' → Variantenartikel
   *
   * @param  array $item
   * @return string 'parent'|'simple'|'variation'
   */
  public function getItemType(array $item): string;

  /**
   * Liefert die Parent-ID, wenn $item eine Variation ist. Sonst null.
   *
   * @param  array $item
   * @return int|null
   */
  public function getParentId(array $item): ?int;

  /**
   * Sucht unterhalb eines Parent-Products eine Variation mit den gegebenen Attributen.
   *
   * Beispiel:
   * - $attributes = ['color' => 'RED', 'size' => 'L']
   * - Implementierung: Hole Variations des Parents und vergleiche die Attribute (Slug/Name harmonisieren!).
   *
   * @param  int   $parentId
   * @param  array $attributes  key=value Paare (z. B. ['color'=>'RED','size'=>'L'])
   * @return int|null           Variation-ID oder null
   */
  public function findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int;

  /**
   * Aktualisiert ein bestehendes Produkt (Parent oder Simple).
   *
   * @param  int   $productId
   * @param  array $payload
   * @return array|null API-Antwort
   */
  public function updateProduct(int $productId, array $payload): ?array;

  /**
   * Aktualisiert eine bestehende Variation unterhalb eines Parent-Products.
   *
   * @param  int   $parentId
   * @param  int   $variationId
   * @param  array $payload
   * @return array|null API-Antwort
   */
  public function updateVariation(int $parentId, int $variationId, array $payload): ?array;

  /**
   * Legt ein neues Produkt (Parent oder Simple) an.
   *
   * @param  array $payload
   * @return array|null API-Antwort (sollte mind. die neue ID enthalten)
   */
  public function createProduct(array $payload): ?array;

  /**
   * Legt eine neue Variation unterhalb eines Parent-Products an.
   *
   * @param  int   $parentId
   * @param  array $payload
   * @return array|null API-Antwort (sollte mind. die neue ID enthalten)
   */
  public function createVariation(int $parentId, array $payload): ?array;
}
