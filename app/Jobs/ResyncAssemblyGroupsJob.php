<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\AssemblyGroupResyncRun;
use App\Services\Product\AssemblyGroupResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

class ResyncAssemblyGroupsJob implements ShouldQueue
{
    use FoundationQueueable;

    public function __construct(public int $runId)
    {
        //
    }

    public function handle(): void
    {
        $run = AssemblyGroupResyncRun::findOrFail($this->runId);

        $run->update(['status' => 'running']);

        $resolver = app(AssemblyGroupResolver::class);

        $query = Product::query()
            ->where(
                fn($q) =>
                $q->whereNull('assembly_group_source')
                    ->orWhere('assembly_group_source', 'auto')
            );

        $total = $query->count();

        $run->update(['total' => $total]);

        $processed = 0;
        $updated = 0;

        $query->chunkById(200, function ($products) use ($resolver, &$processed, &$updated, $run) {
            foreach ($products as $product) {
                $processed++;

                $resolved = $resolver->resolve($product);

                if ((int) $product->assembly_group !== $resolved) {
                    $product->update([
                        'assembly_group' => $resolved,
                        'assembly_group_source' => 'auto',
                    ]);

                    $updated++;
                }
            }
        });

        $run->update([
            'status' => 'finished',
            'processed' => $processed,
            'updated' => $updated,
            'total' => $total,
        ]);
    }
}
