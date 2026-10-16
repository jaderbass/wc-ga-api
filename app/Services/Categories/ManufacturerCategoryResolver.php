<?php

namespace App\Services\Categories;

use App\Models\CategoryAssignmentRule;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Ermittelt die Shop-Kategorien eines Produkts über die Hersteller-Zuordnung.
 *
 * Reihenfolge:
 * 1. Stichwort-Regeln (erst die des Herstellers, dann die für alle Hersteller;
 *    innerhalb nach sort_order) – optional auf eine Herstellerkategorie beschränkt
 * 2. Zuordnung der Herstellerkategorie (products.source_category)
 *
 * Greift nichts, liefert resolve() null und der Aufrufer fällt auf die
 * allgemeinen Stichwort-Regeln ({@see CategoryResolver}) zurück.
 *
 * Stichwörter werden in "Herstellerkategorie + Produktname" gesucht.
 *
 * Stichwort-Syntax: mehrere Stichwörter mit "|" trennen. Ein Stichwort trifft
 * am Wortanfang ("ring" trifft "Ring", nicht "Spring"); mit "*" davor auch
 * mitten im Wort ("*rolle" trifft "Umlenkrolle").
 */
class ManufacturerCategoryResolver
{
    /** @var Collection<int, CategoryAssignmentRule>|null */
    protected ?Collection $keywordRules = null;

    /** @var Collection<int, CategoryAssignmentRule>|null */
    protected ?Collection $sourceRules = null;

    public function resolve(Product $product): ?ManufacturerCategoryDecision
    {
        $manufacturerId = $product->manufacturer_id !== null ? (int) $product->manufacturer_id : null;
        $source = self::normalizeSource($product->source_category);
        // Stichwörter treffen Herstellerkategorie und Produktname
        $text = self::normalizeText(trim($product->source_category.' '.($product->original_product_name ?: $product->product_name)));

        foreach ($this->keywordRules() as $rule) {
            if ($rule->manufacturer_id !== null && (int) $rule->manufacturer_id !== $manufacturerId) {
                continue;
            }

            if (filled($rule->source_category) && self::normalizeSource($rule->source_category) !== $source) {
                continue;
            }

            if (! self::matchesKeyword($text, (string) $rule->keyword) || ! $this->isDecisive($rule)) {
                continue;
            }

            return ManufacturerCategoryDecision::fromRule($rule);
        }

        if ($manufacturerId === null || $source === '') {
            return null;
        }

        $mapping = $this->sourceRules()->first(
            fn (CategoryAssignmentRule $rule): bool => (int) $rule->manufacturer_id === $manufacturerId
                && self::normalizeSource($rule->source_category) === $source
        );

        if ($mapping === null || ! $this->isDecisive($mapping)) {
            return null;
        }

        return ManufacturerCategoryDecision::fromRule($mapping);
    }

    public function flush(): void
    {
        $this->keywordRules = null;
        $this->sourceRules = null;
    }

    /**
     * Prüft, ob ein normalisierter Text eines der Stichwörter enthält.
     */
    public static function matchesKeyword(string $normalizedText, string $keywords): bool
    {
        if ($normalizedText === '') {
            return false;
        }

        foreach (explode('|', $keywords) as $keyword) {
            $anywhere = str_starts_with(ltrim($keyword), '*');
            $keyword = self::normalizeText(ltrim(ltrim($keyword), '*'));

            if ($keyword === '') {
                continue;
            }

            $pattern = $anywhere
                ? '/'.preg_quote($keyword, '/').'/u'
                : '/(^| )'.preg_quote($keyword, '/').'/u';

            if (preg_match($pattern, $normalizedText) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kleinbuchstaben, Satzzeichen zu Leerzeichen, Mehrfach-Leerzeichen zusammenfassen.
     */
    public static function normalizeText(?string $value): string
    {
        $value = mb_strtolower((string) $value, 'UTF-8');
        $value = preg_replace('/[\/\-_,.;:()\[\]"\'+•*]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function normalizeSource(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /**
     * Eine Regel entscheidet nur, wenn sie Kategorien hat oder ausschließt.
     * Leere Einträge (noch nicht zugeordnet) werden übersprungen.
     */
    protected function isDecisive(CategoryAssignmentRule $rule): bool
    {
        return $rule->exclude || $rule->categories->isNotEmpty();
    }

    /**
     * @return Collection<int, CategoryAssignmentRule>
     */
    protected function keywordRules(): Collection
    {
        return $this->keywordRules ??= CategoryAssignmentRule::query()
            ->with('categories')
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->get()
            ->sortBy(fn (CategoryAssignmentRule $rule): array => [
                $rule->manufacturer_id === null ? 1 : 0,
                $rule->sort_order,
                $rule->id,
            ])
            ->values();
    }

    /**
     * @return Collection<int, CategoryAssignmentRule>
     */
    protected function sourceRules(): Collection
    {
        return $this->sourceRules ??= CategoryAssignmentRule::query()
            ->with('categories')
            ->where(fn ($query) => $query->whereNull('keyword')->orWhere('keyword', ''))
            ->whereNotNull('manufacturer_id')
            ->whereNotNull('source_category')
            ->orderBy('id')
            ->get();
    }
}
