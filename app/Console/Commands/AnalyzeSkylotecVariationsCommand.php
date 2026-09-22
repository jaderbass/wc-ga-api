<?php

namespace App\Console\Commands;

use App\Imports\Manufacturer\SkylotecProductGroupResolver;
use Illuminate\Console\Command;
use League\Csv\Reader;

class AnalyzeSkylotecVariationsCommand extends Command
{
    protected $signature = 'skylotec:analyze-variations {file}';

    protected $description = 'Analysiert mögliche Variantenattribute in einer Skylotec-CSV-Datei.';

    /**
     * Analysiert Skylotec-Produktgruppen und prüft, welche Attribute
     * innerhalb einer Gruppe tatsächlich variieren.
     */
    public function handle(): int
    {
        $file = (string) $this->argument('file');

        $path = str_starts_with($file, '/')
            ? $file
            : storage_path('app/imports/'.$file);

        if (! is_file($path)) {
            $this->error("Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        $groups = $rows
            ->groupBy(fn (array $row) => SkylotecProductGroupResolver::resolve($row))
            ->filter(fn ($rows) => $rows->count() > 1);

        $fields = [
            'Dimension',
            'Größe',
            'Kleidergröße',
            'Seillänge',
            'Farbe',
        ];

        $variationCounts = array_fill_keys($fields, 0);

        $dimensionMatchesSize = 0;
        $dimensionMatchesRopeLength = 0;
        $dimensionIndependent = [];
        $dimensionMatchesClothingSize = 0;

        foreach ($groups as $groupKey => $groupRows) {
            foreach ($fields as $field) {
                if ($this->distinctValues($groupRows, $field)->count() > 1) {
                    $variationCounts[$field]++;
                }
            }

            if ($this->distinctValues($groupRows, 'Dimension')->count() <= 1) {
                continue;
            }

            if ($this->fieldsEquivalent($groupRows, 'Dimension', 'Größe')) {
                $dimensionMatchesSize++;

                continue;
            }

            if ($this->fieldsEquivalent($groupRows, 'Dimension', 'Kleidergröße')) {
                $dimensionMatchesClothingSize++;

                continue;
            }

            if ($this->fieldsEquivalent($groupRows, 'Dimension', 'Seillänge')) {
                $dimensionMatchesRopeLength++;

                continue;
            }

            $dimensionIndependent[] = [
                'group' => (string) $groupKey,
                'product' => (string) ($groupRows->first()['Produktname'] ?? ''),
                'dimension' => $this->distinctValues($groupRows, 'Dimension')->implode(' | '),
                'size' => $this->distinctValues($groupRows, 'Größe')->implode(' | '),
                'clothing_size' => $this->distinctValues($groupRows, 'Kleidergröße')->implode(' | '),
                'rope_length' => $this->distinctValues($groupRows, 'Seillänge')->implode(' | '),
                'rows' => $groupRows
                    ->map(fn (array $row) => [
                        'sku' => (string) ($row['Original Skylotec Artikelnummer'] ?? ''),
                        'dimension' => (string) ($row['Dimension'] ?? ''),
                        'size' => (string) ($row['Größe'] ?? ''),
                        'clothing_size' => (string) ($row['Kleidergröße'] ?? ''),
                        'rope_length' => (string) ($row['Seillänge'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $this->newLine();
        $this->info('Skylotec Variantenanalyse');
        $this->line('CSV-Zeilen: '.$rows->count());
        $this->line('Variable Gruppen: '.$groups->count());

        $this->newLine();

        $this->table(
            ['Attribut', 'Gruppen mit unterschiedlichen Werten'],
            collect($variationCounts)
                ->map(fn (int $count, string $field) => [$field, $count])
                ->values()
                ->all()
        );

        $this->newLine();
        $this->line("Dimension entspricht Größe: {$dimensionMatchesSize}");
        $this->line("Dimension entspricht Kleidergröße: {$dimensionMatchesClothingSize}");
        $this->line("Dimension entspricht Seillänge: {$dimensionMatchesRopeLength}");
        $this->line('Dimension eigenständig: '.count($dimensionIndependent));

        foreach ($dimensionIndependent as $group) {
            $this->newLine();

            $this->info(
                $group['group'].' – '.$group['product']
            );

            $this->table(
                [
                    'Artikelnummer',
                    'Dimension',
                    'Größe',
                    'Kleidergröße',
                    'Seillänge',
                ],
                array_map(
                    fn (array $row) => [
                        $row['sku'],
                        $row['dimension'],
                        $row['size'],
                        $row['clothing_size'],
                        $row['rope_length'],
                    ],
                    $group['rows']
                )
            );
        }

        if ($dimensionIndependent !== []) {
            $this->newLine();
            $this->warn('Gruppen mit eigenständiger Dimension:');

            $this->table(
                [
                    'Gruppe',
                    'Produkt',
                    'Dimension',
                    'Größe',
                    'Kleidergröße',
                    'Seillänge',
                ],
                array_map(
                    fn (array $row) => [
                        $row['group'],
                        $row['product'],
                        $row['dimension'],
                        $row['size'],
                        $row['clothing_size'],
                        $row['rope_length'],
                    ],
                    $dimensionIndependent
                )
            );
        }

        return self::SUCCESS;
    }

    /**
     * Liest die CSV-Datei ein und macht doppelte Header eindeutig.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function readRows(string $path): \Illuminate\Support\Collection
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter(';');

        $records = iterator_to_array($csv->getRecords());

        $headers = array_shift($records);

        if (! is_array($headers)) {
            return collect();
        }

        $seen = [];

        $headers = array_map(function ($header) use (&$seen) {
            $header = trim((string) $header);

            $seen[$header] = ($seen[$header] ?? 0) + 1;

            return $seen[$header] === 1
                ? $header
                : $header.'_'.$seen[$header];
        }, $headers);

        return collect($records)->map(function ($row) use ($headers) {
            $row = array_pad(array_values($row), count($headers), null);
            $row = array_slice($row, 0, count($headers));

            return array_combine($headers, $row);
        });
    }

    /**
     * Liefert die unterschiedlichen nicht-leeren Werte eines Feldes.
     */
    private function distinctValues(
        \Illuminate\Support\Collection $rows,
        string $field
    ): \Illuminate\Support\Collection {
        return $rows
            ->pluck($field)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values();
    }

    /**
     * Prüft, ob zwei Felder innerhalb einer Produktgruppe inhaltlich
     * dieselben Variantenwerte beschreiben.
     */
    private function fieldsEquivalent(
        \Illuminate\Support\Collection $rows,
        string $left,
        string $right
    ): bool {
        $compared = 0;

        foreach ($rows as $row) {
            $leftValue = trim((string) ($row[$left] ?? ''));
            $rightValue = trim((string) ($row[$right] ?? ''));

            if ($leftValue === '' || $rightValue === '') {
                continue;
            }

            $compared++;

            if ($this->normalizeComparableValue($leftValue)
                !== $this->normalizeComparableValue($rightValue)) {
                return false;
            }
        }

        return $compared > 0;
    }

    /**
     * Normalisiert Werte ausschließlich für den inhaltlichen Vergleich.
     */
    private function normalizeComparableValue(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = str_replace(',', '.', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        $oneSizeValues = [
            'uniszise',
            'unisize',
            'onesize',
            'einheitsgröße',
            'einheitsgroesse',
        ];

        if (in_array($value, $oneSizeValues, true)) {
            return 'onesize';
        }

        $sizeAliases = [
            'xxxl' => '3xl',
            'xxxxl' => '4xl',
            'xxxxxl' => '5xl',
        ];

        if (isset($sizeAliases[$value])) {
            return $sizeAliases[$value];
        }

        // Millimeterangaben als echte Dimension erhalten.
        if (str_ends_with($value, 'mm')) {
            return $value;
        }

        // Meter-Einheit entfernen.
        $value = preg_replace('/^(-?\d+(?:\.\d+)?)m$/', '$1', $value) ?? $value;

        // Größen-Schreibweisen vereinheitlichen.
        $value = str_replace(['-', '/'], '', $value);

        // Zahlen wie 10.00 -> 10 und 1.50 -> 1.5.
        if (is_numeric($value)) {
            return rtrim(
                rtrim(number_format((float) $value, 6, '.', ''), '0'),
                '.'
            );
        }

        return $value;
    }
}
