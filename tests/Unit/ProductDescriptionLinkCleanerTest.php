<?php

namespace Tests\Unit;

use App\Support\Html\ProductDescriptionLinkCleaner;
use Tests\TestCase;

class ProductDescriptionLinkCleanerTest extends TestCase
{
    public function test_it_removes_specific_links_but_keeps_text(): void
    {
        $html = '<p>Text <a href="/foo">Mittenmarkierung</a> und <a href="/bar">NFC Chip</a></p>';

        $result = app(ProductDescriptionLinkCleaner::class)->clean($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringContainsString('Mittenmarkierung', $result);
        $this->assertStringContainsString('NFC Chip', $result);
    }

    public function test_it_keeps_other_links(): void
    {
        $html = '<p><a href="/foo">Produktseite</a></p>';

        $result = app(ProductDescriptionLinkCleaner::class)->clean($html);

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('Produktseite', $result);
    }

    public function test_it_handles_mixed_content(): void
    {
        $html = '<p><a href="/a">Mittenmarkierung</a> und <a href="/b">Andere Info</a></p>';

        $result = app(ProductDescriptionLinkCleaner::class)->clean($html);

        $this->assertStringNotContainsString('href="/a"', $result);
        $this->assertStringContainsString('Mittenmarkierung', $result);

        $this->assertStringContainsString('href="/b"', $result);
    }

    public function test_it_is_case_insensitive(): void
    {
        $html = '<a href="#">nfc</a>';

        $result = app(ProductDescriptionLinkCleaner::class)->clean($html);

        $this->assertStringNotContainsString('<a', $result);
    }
}
