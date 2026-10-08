<?php

namespace App\Support\Imports;

use League\Csv\Reader;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Liest die Zeilen des ersten Tabellenblatts einer .xlsx- oder .csv-Datei.
 *
 * Bewusst ohne zusätzliche Bibliothek (PhpSpreadsheet): .xlsx ist ein ZIP mit
 * XML-Dateien, mehr als Text und Zahlen brauchen die Herstellerlisten nicht.
 * Zahlen werden als Text geliefert, so wie sie in der Datei stehen
 * (Artikel- und EAN-Nummern bleiben dadurch vollständig).
 */
class SpreadsheetRowReader
{
    /**
     * @return list<list<string|null>>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Datei nicht gefunden: {$path}");
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            default => throw new RuntimeException('Nur .xlsx oder .csv werden unterstützt.'),
        };
    }

    /**
     * @return list<list<string|null>>
     */
    protected static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $firstLine = (string) fgets($handle);
        fclose($handle);

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter($delimiter);

        $rows = [];

        foreach ($csv->getRecords() as $record) {
            $rows[] = array_map(
                fn ($value) => ($value = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))) === '' ? null : $value,
                array_values($record),
            );
        }

        return $rows;
    }

    /**
     * @return list<list<string|null>>
     */
    protected static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Excel-Datei kann nicht geöffnet werden: {$path}");
        }

        try {
            $sharedStrings = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));

            if ($sheetXml === false) {
                throw new RuntimeException('Kein Tabellenblatt in der Excel-Datei gefunden.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
        } finally {
            $zip->close();
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int) $row['r'] - 1;
            $cells = [];

            foreach ($row->c as $cell) {
                $column = self::columnIndex((string) $cell['r']);
                $cells[$column] = self::cellValue($cell, $sharedStrings);
            }

            $values = [];
            $maxColumn = $cells === [] ? -1 : max(array_keys($cells));

            for ($i = 0; $i <= $maxColumn; $i++) {
                $values[] = $cells[$i] ?? null;
            }

            // fehlende (leere) Zeilen auffüllen, damit Zeilennummern stimmen
            while (count($rows) < $rowIndex) {
                $rows[] = [];
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    protected static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach ((new SimpleXMLElement($xml))->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;

                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    protected static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbookXml = new SimpleXMLElement($workbook);
        $sheet = $workbookXml->sheets->sheet[0] ?? null;
        $relationId = $sheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? null;

        foreach ((new SimpleXMLElement($rels))->Relationship as $relationship) {
            if ((string) $relationship['Id'] === (string) $relationId) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    protected static function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell['t'];

        $value = match ($type) {
            's' => $sharedStrings[(int) $cell->v] ?? null,
            'inlineStr' => (string) $cell->is->t,
            default => isset($cell->v) ? (string) $cell->v : null,
        };

        $value = $value !== null ? str_replace('_x000D_', '', $value) : null;
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /**
     * "A1" → 0, "B7" → 1, "AA3" → 26
     */
    protected static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($reference)) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
