<?php

namespace App\Http\Controllers;

use App\Models\PetzlDescriptionSyncRun;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PetzlDescriptionSyncRunEventsController extends Controller
{
    public function show(PetzlDescriptionSyncRun $run): StreamedResponse
    {
        return response()->stream(function () use ($run) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            ob_implicit_flush(true);

            $start = time();
            $timeoutSeconds = 600;

            while (true) {
                $fresh = PetzlDescriptionSyncRun::query()->find($run->id);

                if (! $fresh) {
                    echo "event: error\n";
                    echo 'data: {"status":"missing"}' . "\n\n";
                    flush();

                    break;
                }

                $batch = $fresh->batch_id
                    ? Bus::findBatch($fresh->batch_id)
                    : null;

                $total = (int) ($batch?->totalJobs ?? 0);
                $pending = (int) ($batch?->pendingJobs ?? 0);
                $failed = (int) ($batch?->failedJobs ?? 0);

                /*
                 * Bei allowFailures() bleiben fehlgeschlagene Jobs
                 * in pendingJobs enthalten.
                 */
                $successful = max(0, $total - $pending);
                $completed = min($total, $successful + $failed);

                $progress = $total > 0
                    ? (int) round(($completed / $total) * 100)
                    : ($fresh->status === 'done' ? 100 : 0);

                echo "event: status\n";
                echo 'data: ' . json_encode([
                    'id' => $fresh->id,
                    'status' => $fresh->status,
                    'mode' => $fresh->mode,
                    'trigger' => $fresh->trigger,

                    'total' => $total,
                    'completed' => $completed,
                    'successful' => $successful,
                    'failed' => $failed,
                    'progress' => $progress,

                    'started_at' => optional($fresh->started_at)->toIso8601String(),
                    'finished_at' => optional($fresh->finished_at)->toIso8601String(),
                    'error_message' => $fresh->error_message,
                ]) . "\n\n";

                flush();

                if (in_array($fresh->status, ['done', 'failed'], true)) {
                    break;
                }

                if ((time() - $start) >= $timeoutSeconds) {
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none',
        ]);
    }
}