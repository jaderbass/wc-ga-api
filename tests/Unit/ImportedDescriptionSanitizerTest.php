<?php

namespace Tests\Unit;

use App\Services\ProductDescriptions\ImportedDescriptionSanitizer;
use PHPUnit\Framework\TestCase;

class ImportedDescriptionSanitizerTest extends TestCase
{
    private ImportedDescriptionSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new ImportedDescriptionSanitizer;
    }

    public function test_it_unwraps_normal_text_links(): void
    {
        $html = '<p>Passend zum <a href="950-kletterhelm-penta.html">Penta</a>.</p>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringContainsString('Passend zum Penta.', $result);
    }

    public function test_it_removes_linked_images_completely(): void
    {
        $html = <<<'HTML'
<div>
    <a href="../content/8-symbolerklaerung">
        <img src="../img/cms/Individuelle_Nummerierung.png" alt="Individuelle Nummerierung">
    </a>
</div>
<p>Beschreibung</p>
HTML;

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringNotContainsString('Individuelle_Nummerierung.png', $result);
        $this->assertStringContainsString('Beschreibung', $result);
    }

    public function test_it_removes_empty_links(): void
    {
        $html = '<p>Text <a href="../content/test"></a> danach.</p>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringContainsString('Text ', $result);
        $this->assertStringContainsString(' danach.', $result);
    }

    public function test_it_removes_meterware_cta_with_container(): void
    {
        $html = <<<'HTML'
<p>Beschreibung</p>
<div style="padding: 8px; background-color: #c3b70d;">
    <a href="https://www.aliens-outdoor.com/test" target="_blank">Auch als Meterware erhältlich</a>
</div>
<p>Danach</p>
HTML;

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringNotContainsString('Auch als Meterware erhältlich', $result);
        $this->assertStringNotContainsString('#c3b70d', $result);

        $this->assertStringContainsString('Beschreibung', $result);
        $this->assertStringContainsString('Danach', $result);
    }

    public function test_it_preserves_text_from_external_links(): void
    {
        $html = '<p>Weitere Informationen bei <a href="https://example.com">Hersteller</a>.</p>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<a', $result);
        $this->assertStringNotContainsString('href=', $result);
        $this->assertStringContainsString(
            'Weitere Informationen bei Hersteller.',
            $result
        );
    }
}
