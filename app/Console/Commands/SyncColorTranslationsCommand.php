<?php

namespace App\Console\Commands;

use App\Models\ColorTranslation;
use App\Models\ProductAttributeValue;
use Illuminate\Console\Command;

class SyncColorTranslationsCommand extends Command
{
    protected $signature = 'colors:sync';

    protected $description = 'Übernimmt alle vorhandenen Farbwerte in die Farb-Übersetzungen (Grundfarben werden automatisch übersetzt).';

    public function handle(): int
    {
        $before = ColorTranslation::count();

        ProductAttributeValue::query()
            ->whereHas('attribute', fn ($q) => $q->whereIn('slug', ColorTranslation::COLOR_ATTRIBUTE_SLUGS))
            ->whereHas('variations')
            ->orderBy('id')
            ->chunkById(500, function ($values): void {
                foreach ($values as $value) {
                    ColorTranslation::ensureFor((string) $value->value);
                }
            });

        $after = ColorTranslation::count();
        $missing = ColorTranslation::query()
            ->where(fn ($q) => $q->whereNull('translated_value')->orWhere('translated_value', ''))
            ->count();

        $this->info('Neu aufgenommen: '.($after - $before)." | Gesamt: {$after} | Noch ohne Übersetzung: {$missing}");

        return self::SUCCESS;
    }
}
