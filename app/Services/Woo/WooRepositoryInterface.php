<?php

namespace App\Services\Woo;

/**
 * Interface WooRepositoryInterface
 *
 * Abstraktionsschicht über WooClient für Lookup- & Mutations-Operationen.
 * Wird u. a. vom WooParentResolver und ProductExportOrchestrator verwendet.
 *
 * Wichtige Konventionen:
 * - SKU-first Matching (gemäß Kunden-Vorgabe).
 * - Für Varianten-Attribute werden Taxonomie-Slugs (z. B. "pa_color") verwendet.
 * - Rückgaben sind i. d. R. assoziative Arrays, wie sie der Woo REST liefert.
 */
interface WooRepositoryInterface
{
  // ---------------------------------------------------------
  // Lookup
  // ---------------------------------------------------------

  /**
   * Finde Produkt oder Variation anhand einer exakten SKU.
   *
   * @param  string $sku
   * @return array|null  Produkt- oder Variations-Response
   */
  public function findBySku(string $sku): ?array;

  /**
   * Finde Produkt/Variation anhand einer EAN (über Meta/Attribute).
   *
   * @param  string $ean
   * @return array|null
   */
  public function findByEan(string $ean): ?array;

  /**
   * Finde Produkt/Variation anhand einer MPN (über Meta/Attribute).
   *
   * @param  string $mpn
   * @return array|null
   */
  public function findByMpn(string $mpn): ?array;

  /**
   * Liefert den Item-Typ: 'simple' | 'parent' | 'variation'.
   *
   * @param  array $item
   * @return string
   */
  public function getItemType(array $item): string;

  /**
   * Liefert die ID eines Produktes/Variation.
   *
   * @param  array $item
   * @return int
   */
  public function getItemId(array $item): int;

  /**
   * Liefert die Parent-ID (für Variationen), sonst null.
   *
   * @param  array $item
   * @return int|null
   */
  public function getParentId(array $item): ?int;

  /**
   * Sucht unter einem Parent die Variation, deren Attribute (Taxonomie-Slugs)
   * den erwarteten Paaren entsprechen.
   *
   * Beispiel $attributes: ['color' => 'Red', 'size' => 'L']
   *
   * @param  int   $parentId
   * @param  array $attributes
   * @return int|null  Variation-ID
   */
  public function findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int;

  // ---------------------------------------------------------
  // Mutations
  // ---------------------------------------------------------

  /**
   * Aktualisiert ein Produkt (Simple/Parent).
   *
   * @param  int   $productId
   * @param  array $payload
   * @return array|null
   */
  public function updateProduct(int $productId, array $payload): ?array;

  /**
   * Aktualisiert eine Variation unter einem Parent.
   *
   * @param  int   $parentId
   * @param  int   $variationId
   * @param  array $payload
   * @return array|null
   */
  public function updateVariation(int $parentId, int $variationId, array $payload): ?array;

  /**
   * Erstellt ein Produkt (Simple/Parent).
   *
   * @param  array $payload
   * @return array|null
   */
  public function createProduct(array $payload): ?array;

  /**
   * Erstellt eine Variation unter einem Parent.
   *
   * @param  int   $parentId
   * @param  array $payload
   * @return array|null
   */
  public function createVariation(int $parentId, array $payload): ?array;

  // ---------------------------------------------------------
  // Attribute / Taxonomien
  // ---------------------------------------------------------

  /**
   * Liefert die **Attribut-ID** zu einem Taxonomie-Slug (z. B. "pa_color").
   * Darf null liefern, wenn nicht implementiert/gefunden – der Orchestrator fällt dann
   * auf Namen/Slugs im Payload zurück.
   *
   * @param  string $slug
   * @return int|null
   */
  public function getAttributeIdBySlug(string $slug): ?int;
}
