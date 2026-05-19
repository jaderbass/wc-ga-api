<?php

namespace App\Services\Petzl;

use App\Models\PetzlTranslation;

class PetzlCsvTranslationService
{
    public function translateCategory(?string $value): ?string
    {
        return $this->translate('Category', $value);
    }

    public function translateDesignation(?string $value): ?string
    {
        return $this->translate('Designation', $value);
    }

    public function translate(string $sourceColumn, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $translation = PetzlTranslation::query()
            ->where('source_column', $sourceColumn)
            ->where('source_text', $value)
            ->where('source_lang', 'EN')
            ->where('target_lang', 'DE')
            ->first();

        return $translation?->translated_text ?: $value;
    }

    public function rememberTerm(string $sourceColumn, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        PetzlTranslation::query()->firstOrCreate([
            'source_column' => $sourceColumn,
            'source_text' => $value,
            'source_lang' => 'EN',
            'target_lang' => 'DE',
        ]);
    }
}
