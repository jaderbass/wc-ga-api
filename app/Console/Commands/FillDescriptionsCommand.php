<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\TextNormalizer;
use Illuminate\Console\Command;

/**
 * Leere Shop-Beschreibungen aus vorhandenen Quellen füllen.
 *
 * - Petzl: von petzl.com geholte Texte (petzl_description_html / petzl_short_description_html)
 * - Kurzbeschreibung leer, Beschreibung vorhanden → erster Satz der Beschreibung
 *   (Kurzbeschreibung = Warenkorbbeschreibung)
 *
 * Vorhandene Texte werden nie überschrieben.
 */
class FillDescriptionsCommand extends Command
{
    protected $signature = 'products:fill-descriptions {--dry-run : Nur zählen, nichts speichern}';

    protected $description = 'Leere Beschreibungen aus Petzl-Texten bzw. Kurzbeschreibung aus dem ersten Satz füllen';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['Beschreibung aus Petzl-Text' => 0, 'Kurzbeschreibung aus Petzl-Text' => 0, 'Kurzbeschreibung aus erstem Satz' => 0];

        Product::query()
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', '')
                ->orWhereNull('short_description')->orWhere('short_description', ''))
            ->chunkById(200, function ($products) use ($dryRun, &$counts): void {
                foreach ($products as $product) {
                    $changes = [];

                    if (blank(TextNormalizer::plain((string) $product->description)) && filled(TextNormalizer::plain((string) $product->petzl_description_html))) {
                        $changes['description'] = $product->petzl_description_html;
                        $counts['Beschreibung aus Petzl-Text']++;
                    }

                    if (blank(TextNormalizer::plain((string) $product->short_description))) {
                        if (filled(TextNormalizer::plain((string) $product->petzl_short_description_html))) {
                            $changes['short_description'] = $product->petzl_short_description_html;
                            $counts['Kurzbeschreibung aus Petzl-Text']++;
                        } elseif (($first = TextNormalizer::firstSentence($changes['description'] ?? $product->description)) !== null) {
                            $changes['short_description'] = $first;
                            $counts['Kurzbeschreibung aus erstem Satz']++;
                        }
                    }

                    if (! $dryRun && $changes !== []) {
                        $product->forceFill($changes)->saveQuietly();
                    }
                }
            });

        $this->line(($dryRun ? '[Probelauf – nichts gespeichert] ' : '').'Ergebnis:');

        foreach ($counts as $label => $count) {
            $this->line(str_pad($label.':', 36).($count > 0 ? "<fg=green>{$count}</>" : '0'));
        }

        return self::SUCCESS;
    }
}
