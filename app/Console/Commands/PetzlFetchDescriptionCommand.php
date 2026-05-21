<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Petzl\PetzlDescriptionFetcher;

class PetzlFetchDescriptionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'petzl:fetch-description
        {url : Petzl product URL}
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

        $this->info('URL: ' . $result['url']);
        $this->info('Hash: ' . $result['hash']);
        $this->line('');
        $this->line($result['description_html']);

        return self::SUCCESS;
    }
}
