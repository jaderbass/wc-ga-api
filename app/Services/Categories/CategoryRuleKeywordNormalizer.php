<?php

namespace App\Services\Categories;

class CategoryRuleKeywordNormalizer
{
    /**
     * Normalisiert ein Regel-Keyword für Kategorien.
     *
     * - trimmt führende und nachfolgende Leerzeichen
     * - reduziert Mehrfach-Leerzeichen auf ein Leerzeichen
     * - wandelt in Kleinbuchstaben um
     *
     * @param string|null $keyword
     * @return string
     */
    public static function normalize(?string $keyword): string
    {
        $value = mb_strtolower(trim((string) $keyword), 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
