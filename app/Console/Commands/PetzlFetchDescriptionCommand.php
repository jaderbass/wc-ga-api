<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\Petzl\PetzlDescriptionFetcher;
use App\Services\Petzl\PetzlDescriptionImportService;
use App\Services\Petzl\PetzlProductUrlResolver;
use Illuminate\Support\Str;

class PetzlFetchDescriptionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'petzl:fetch-description
        {url? : Petzl product URL}
        {--reference= : Petzl reference}
        {--product-id= : Product ID}
        {--dry-run : Only output result without storing}
        {--product-name= : Petzl product name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function handle(
        PetzlDescriptionFetcher $fetcher,
        PetzlDescriptionImportService $importService,
    ): int {
        $url = (string) $this->argument('url');
        $productId = $this->option('product-id');

        if (! $url && $this->option('reference')) {
            $url = app(PetzlProductUrlResolver::class)
                ->resolveByReference(
                    (string) $this->option('reference')
                );
        }

        if (! $url && $this->option('product-name')) {
            $url = app(PetzlProductUrlResolver::class)
                ->resolveByProductName((string) $this->option('product-name'));
        }

        if ($this->option('dry-run')) {
            $result = $fetcher->fetchFromUrl($url);
        } else {
            if (! $productId) {
                $this->error('Option --product-id is required unless --dry-run is used.');

                return self::FAILURE;
            }

            $product = Product::findOrFail((int) $productId);
            $result = $importService->importFromUrl($product, $url);

            $this->info('Product updated: ' . $product->id);
        }

        file_put_contents(
            storage_path('app/petzl-debug.html'),
            $result['description_html']
        );

        $this->info('URL: ' . $result['url']);
        $this->info('Hash: ' . $result['hash']);
        $this->line('');
        $this->line('===== HTML =====');
        $this->line(Str::limit(strip_tags($result['description_html']), 1000));

        return self::SUCCESS;
    }
}
