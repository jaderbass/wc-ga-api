<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\NameTemplateRegistry;
use App\Services\ProductNaming\ProductNameContext;
use Illuminate\Console\Command;

class ProductNamePreviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:name-preview
        {productId : Die ID des Produkts}
        {--debug : Zeigt zusätzliche Debug-Informationen an}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zeigt eine Vorschau auf die zusammengesetzte Produktbenennung';

    public function handle(DefaultProductNameBuilder $builder, NameTemplateRegistry $registry): int
    {
        $productId = (int) $this->argument('productId');
        $debug = (bool) $this->option('debug');

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

        $this->line('');
        $this->info('Produktname-Vorschau');
        $this->line(str_repeat('-', 60));

        $this->line('Produkt-ID:    ' . $product->id);
        $this->line('Produkttyp:    ' . ($product->product_type ?? '-'));
        $this->line('Kind:          ' . $ctx->kind->value);
        $this->line('Hersteller:    ' . $ctx->manufacturerName);
        $this->line('Kategorie:     ' . ($ctx->categoryName !== '' ? $ctx->categoryName : '-'));
        $this->line('Bezeichnung:   ' . $ctx->designation);
        $this->line('Eigenschaften: ' . ($ctx->properties !== [] ? implode(' | ', array_filter($ctx->properties)) : '-'));
        $this->line('Ergebnis:      ' . $result->productName);

        if ($debug) {
            $this->line('');
            $this->info('Debug');
            $this->line(str_repeat('-', 60));
            $this->line('Separator:     ' . ($template['separator'] ?? ' - '));
            $this->line('Template:      ' . implode(', ', $template['template'] ?? []));
            $this->line('Parts:         ' . ($result->parts !== [] ? implode(' | ', $result->parts) : '-'));
            $this->line('ManufacturerID:' . ($ctx->manufacturerId ?? '-'));
        }

        $this->line('');

        return self::SUCCESS;
    }
}
