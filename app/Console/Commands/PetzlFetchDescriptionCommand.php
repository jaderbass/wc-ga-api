<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Petzl\PetzlDescriptionFetcher;
use Illuminate\Support\Str;

class PetzlFetchDescriptionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'petzl:fetch-description
        {url : Petzl product URL}
        {--product-id= : Product ID to update}
        {--dry-run : Only output result without storing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function handle(PetzlDescriptionFetcher $fetcher): int
    {
        $url = (string) $this->argument('url');

        $result = $fetcher->fetchFromUrl($url);

        if (! $this->option('dry-run') && $this->option('product-id')) {
            $product = \App\Models\Product::findOrFail($this->option('product-id'));

            $product->forceFill([
                'petzl_description_html' => $result['description_html'],
                'petzl_description_source_url' => $result['url'],
                'petzl_description_fetched_at' => now(),
                'petzl_description_hash' => $result['hash'],
                'description_source' => $product->description_source === 'manual'
                    ? 'manual'
                    : 'auto',
            ])->save();

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
        $this->line(Str::limit(
            strip_tags($result['description_html']),
            1000
        ));

        return self::SUCCESS;
    }
}
