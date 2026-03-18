<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\NameTemplateRegistry;
use App\Services\ProductNaming\ProductKind;
use App\Services\ProductNaming\ProductNameContext;
use Illuminate\Console\Command;

class ProductNamePreviewCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'product:name-preview
        {productId? : Die ID des Produkts}
        {--debug : Zeigt zusätzliche Debug-Informationen an}
        {--case= : Führt einen benannten Testfall aus}
        {--list-cases : Listet alle verfügbaren Testfälle auf}';

    /**
     * @var string
     */
    protected $description = 'Zeigt eine Vorschau auf die zusammengesetzte Produktbenennung';

    public function handle(DefaultProductNameBuilder $builder, NameTemplateRegistry $registry): int
    {
        $debug = (bool) $this->option('debug');

        if ((bool) $this->option('list-cases')) {
            return $this->listCases();
        }

        $caseKey = $this->option('case');
        if (is_string($caseKey) && trim($caseKey) !== '') {
            return $this->previewCase(trim($caseKey), $builder, $registry, $debug);
        }

        $productId = $this->argument('productId');
        if ($productId === null) {
            $this->error('Bitte eine Produkt-ID angeben oder --case bzw. --list-cases verwenden.');

            return self::FAILURE;
        }

        return $this->previewProduct((int) $productId, $builder, $registry, $debug);
    }

    private function previewProduct(
        int $productId,
        DefaultProductNameBuilder $builder,
        NameTemplateRegistry $registry,
        bool $debug
    ): int {
        $product = Product::query()
            ->with(['manufacturer', 'variations'])
            ->find($productId);

        if (!$product) {
            $this->error("Produkt mit ID {$productId} wurde nicht gefunden.");

            return self::FAILURE;
        }

        $ctx = ProductNameContext::fromProduct($product);
        $result = $builder->build($ctx);
        $template = $registry->get($ctx);

        $this->renderPreview(
            source: 'Produkt',
            label: (string) $product->id,
            ctx: $ctx,
            productType: (string) ($product->product_type ?? '-'),
            resultName: $result->productName,
            parts: $result->parts,
            template: $template,
            expected: null,
            debug: $debug,
        );

        return self::SUCCESS;
    }

    private function previewCase(
        string $caseKey,
        DefaultProductNameBuilder $builder,
        NameTemplateRegistry $registry,
        bool $debug
    ): int {
        /** @var array<string, array<string, mixed>> $cases */
        $cases = require base_path('tests/Datasets/ProductNamingCases.php');

        $case = $cases[$caseKey] ?? null;

        if (!is_array($case)) {
            $this->error("Unbekannter Testfall: {$caseKey}");
            $this->newLine();
            $this->info('Verfügbare Testfälle:');
            foreach (array_keys($cases) as $key) {
                $this->line(' - ' . $key);
            }

            return self::FAILURE;
        }

        /** @var array<string, mixed> $input */
        $input = (array) ($case['input'] ?? []);
        /** @var array<int, string|null> $properties */
        $properties = array_values((array) ($input['properties'] ?? []));

        $kindValue = (string) ($input['kind'] ?? 'simple');
        $kind = ProductKind::from($kindValue);

        $ctx = new ProductNameContext(
            kind: $kind,
            manufacturerName: (string) ($input['manufacturer'] ?? ''),
            categoryName: (string) ($input['category'] ?? ''),
            designation: (string) ($input['designation'] ?? ''),
            properties: $properties,
            manufacturerId: null,
        );

        $result = $builder->build($ctx);
        $template = $registry->get($ctx);
        $expected = (string) ($case['expected'] ?? '');

        $this->renderPreview(
            source: 'Testfall',
            label: (string) ($case['label'] ?? $caseKey),
            ctx: $ctx,
            productType: '-',
            resultName: $result->productName,
            parts: $result->parts,
            template: $template,
            expected: $expected,
            debug: $debug,
        );

        if ($expected !== '') {
            $this->line('Erwartet:      ' . $expected);
            $this->line('Vergleich:     ' . ($result->productName === $expected ? 'OK' : 'ABWEICHUNG'));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function listCases(): int
    {
        /** @var array<string, array<string, mixed>> $cases */
        $cases = require base_path('tests/Datasets/ProductNamingCases.php');

        $this->info('Verfügbare Naming-Testfälle');
        $this->line(str_repeat('-', 60));

        foreach ($cases as $key => $case) {
            $label = (string) ($case['label'] ?? $key);
            $this->line($key . '  =>  ' . $label);
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $parts
     * @param array{separator:string, template:array<int, string>} $template
     */
    private function renderPreview(
        string $source,
        string $label,
        ProductNameContext $ctx,
        string $productType,
        string $resultName,
        array $parts,
        array $template,
        ?string $expected,
        bool $debug
    ): void {
        $this->newLine();
        $this->info('Produktname-Vorschau');
        $this->line(str_repeat('-', 60));

        $this->line('Quelle:        ' . $source);
        $this->line('Referenz:      ' . $label);
        $this->line('Produkttyp:    ' . $productType);
        $this->line('Kind:          ' . $ctx->kind->value);
        $this->line('Hersteller:    ' . $ctx->manufacturerName);
        $this->line('Kategorie:     ' . ($ctx->categoryName !== '' ? $ctx->categoryName : '-'));
        $this->line('Bezeichnung:   ' . $ctx->designation);
        $this->line('Eigenschaften: ' . ($ctx->properties !== [] ? implode(' | ', array_filter($ctx->properties)) : '-'));
        $this->line('Ergebnis:      ' . $resultName);

        if ($expected !== null && $expected !== '') {
            $this->line('Erwartet:      ' . $expected);
        }

        if ($debug) {
            $this->newLine();
            $this->info('Debug');
            $this->line(str_repeat('-', 60));
            $this->line('Separator:     ' . ($template['separator'] ?? ' - '));
            $this->line('Template:      ' . implode(', ', $template['template'] ?? []));
            $this->line('Parts:         ' . ($parts !== [] ? implode(' | ', $parts) : '-'));
            $this->line('ManufacturerID:' . ($ctx->manufacturerId ?? '-'));
        }
    }
}
