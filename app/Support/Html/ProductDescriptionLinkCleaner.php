<?php

namespace App\Support\Html;

use DOMDocument;
use DOMXPath;

class ProductDescriptionLinkCleaner
{
    /**
     * Entfernt bestimmte Links aus Produktbeschreibungen,
     * behält aber den sichtbaren Linktext bei.
     */
    public function clean(?string $html): ?string
    {
        if (blank($html)) {
            return $html;
        }

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//a') as $link) {
            $label = trim($link->textContent ?? '');

            if (! preg_match('/\b(Mittenmarkierung|NFC)\b/iu', $label)) {
                continue;
            }

            $textNode = $dom->createTextNode($link->textContent);
            $link->parentNode->replaceChild($textNode, $link);
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);

        $cleaned = '';

        foreach ($wrapper->childNodes as $child) {
            $cleaned .= $dom->saveHTML($child);
        }

        return $cleaned;
    }
}
