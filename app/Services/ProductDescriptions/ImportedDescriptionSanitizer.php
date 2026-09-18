<?php

namespace App\Services\ProductDescriptions;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class ImportedDescriptionSanitizer
{
    private const CTA_TEXTS = [
        'Auch als Meterware erhältlich',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . '<div id="description-root">'
            . $html
            . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        /** @var DOMElement[] $links */
        $links = iterator_to_array(
            $xpath->query('//*[@id="description-root"]//a')
        );

        foreach ($links as $link) {
            if (! $link->parentNode) {
                continue;
            }

            /*
             * Verlinkte Bilder vollständig entfernen.
             */
            if ($link->getElementsByTagName('img')->length > 0) {
                $parent = $link->parentNode;
                $parent->removeChild($link);

                $this->removeEmptyAncestors($parent);

                continue;
            }

            /*
             * Bekannte CTA-/Button-Links samt umgebendem
             * Container entfernen.
             */
            if ($this->isCta($link)) {
                $this->removeCta($link);

                continue;
            }

            /*
            * Leere Links vollständig entfernen und dadurch
            * leer gewordene Container ebenfalls bereinigen.
            */
            if (trim($link->textContent) === '' && $link->childNodes->length === 0) {
                $parent = $link->parentNode;
                $parent->removeChild($link);

                $this->removeEmptyAncestors($parent);

                continue;
            }

            /*
             * Normale Textlinks entlinken:
             *
             * <a href="...">Penta</a>
             *
             * wird zu:
             *
             * Penta
             */
            $this->unwrap($link);
        }

        $this->removeEmptyContainers($xpath);

        $root = $dom->getElementById('description-root');

        if (! $root) {
            return $html;
        }

        return $this->innerHtml($root);
    }

    private function isCta(DOMElement $link): bool
    {
        $text = preg_replace('/\s+/u', ' ', trim($link->textContent));

        return in_array($text, self::CTA_TEXTS, true);
    }

    private function removeCta(DOMElement $link): void
    {
        $parent = $link->parentNode;

        if (! $parent) {
            return;
        }

        /*
         * Ist der Link der einzige relevante Inhalt eines typischen
         * Containers, entfernen wir den kompletten Container.
         */
        if (
            $parent instanceof DOMElement
            && in_array(strtolower($parent->tagName), ['div', 'p', 'span'], true)
            && $this->containsOnlyNode($parent, $link)
        ) {
            $grandParent = $parent->parentNode;

            if ($grandParent) {
                $grandParent->removeChild($parent);
                $this->removeEmptyAncestors($grandParent);
            }

            return;
        }

        $parent->removeChild($link);
        $this->removeEmptyAncestors($parent);
    }

    private function containsOnlyNode(DOMNode $parent, DOMNode $expected): bool
    {
        foreach ($parent->childNodes as $child) {
            if ($child === $expected) {
                continue;
            }

            if (
                $child->nodeType === XML_TEXT_NODE
                && trim(str_replace("\xc2\xa0", '', $child->textContent)) === ''
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function removeEmptyAncestors(DOMNode $node): void
    {
        while (
            $node instanceof DOMElement
            && $node->getAttribute('id') !== 'description-root'
            && in_array(strtolower($node->tagName), ['div', 'p', 'span'], true)
            && $this->isEmpty($node)
        ) {
            $parent = $node->parentNode;

            if (! $parent) {
                break;
            }

            $parent->removeChild($node);
            $node = $parent;
        }
    }

    private function isEmpty(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = str_replace("\xc2\xa0", '', $child->textContent);

                if (trim($text) !== '') {
                    return false;
                }

                continue;
            }

            if ($child instanceof DOMElement) {
                return false;
            }

            return false;
        }

        return true;
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private function removeEmptyContainers(DOMXPath $xpath): void
    {
        do {
            $removed = false;

            /** @var DOMElement[] $elements */
            $elements = iterator_to_array(
                $xpath->query(
                    '//*[@id="description-root"]//*[self::div or self::span or self::p]'
                )
            );

            foreach (array_reverse($elements) as $element) {
                if (!$this->isEmpty($element)) {
                    continue;
                }

                if ($this->hasVisualStyle($element)) {
                    continue;
                }

                $parent = $element->parentNode;

                if (!$parent) {
                    continue;
                }

                $parent->removeChild($element);
                $removed = true;
            }
        } while ($removed);
    }

    private function hasVisualStyle(DOMElement $element): bool
    {
        $style = strtolower($element->getAttribute('style'));

        return str_contains($style, 'border')
            || str_contains($style, 'background');
    }
}
