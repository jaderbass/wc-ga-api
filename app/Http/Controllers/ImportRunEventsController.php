<?php

namespace App\Http\Controllers;

use App\Models\ImportRun;
use App\Models\PetzlDescriptionSyncRun;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportRunEventsController extends Controller
{
  public function show(ImportRun $run, Request $request): StreamedResponse
  {
      $waitForPetzlSync = $request->boolean('wait_for_petzl_sync');

      return response()->stream(function () use ($run, $waitForPetzlSync) {
          $start = time();
          $timeoutSeconds = 120;
          $syncWaitStartedAt = null;

          while (true) {
              $fresh = ImportRun::query()->find($run->id);

              if (! $fresh) {
                  echo "event: error\n";
                  echo 'data: {"status":"missing"}' . "\n\n";
                  @ob_flush();
                  @flush();

                  break;
              }

              $syncRun = null;
              $syncWaiting = false;
              $syncUnavailable = false;

              if (
                  $fresh->status === 'done'
                  && $waitForPetzlSync
              ) {
                  $syncRun = PetzlDescriptionSyncRun::query()
                      ->where('import_run_id', $fresh->id)
                      ->latest()
                      ->first();

                  if (! $syncRun) {
                      $syncWaitStartedAt ??= time();

                      if ((time() - $syncWaitStartedAt) < 10) {
                          $syncWaiting = true;
                      } else {
                          $syncUnavailable = true;
                      }
                  }
              }

              echo "event: status\n";
              echo 'data: ' . json_encode([
                  'id' => $fresh->id,
                  'status' => $fresh->status,
                  'processed_rows' => (int) $fresh->processed_rows,
                  'finished_at' => optional($fresh->finished_at)->toIso8601String(),
                  'error_message' => $fresh->error_message,

                  'petzl_sync_run_id' => $syncRun?->id,
                  'petzl_sync_waiting' => $syncWaiting,
                  'petzl_sync_unavailable' => $syncUnavailable,
              ]) . "\n\n";

              @ob_flush();
              @flush();

              if ($fresh->status === 'failed') {
                  break;
              }

              if ($fresh->status === 'done') {
                if (! $waitForPetzlSync) {
                    break;
                }

                if ($syncRun || $syncUnavailable) {
                    break;
                }
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
    ]);
}
}
