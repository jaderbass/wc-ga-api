<?php

namespace App\Http\Controllers;

use App\Models\ImportRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportRunEventsController extends Controller
{
  public function show(ImportRun $run): StreamedResponse
  {
    return response()->stream(function () use ($run) {
      // SSE headers are set outside this callback

      $start = time();
      $timeoutSeconds = 120;

      while (true) {
        $fresh = ImportRun::query()->find($run->id);

        if (!$fresh) {
          echo "event: error\n";
          echo 'data: {"status":"missing"}' . "\n\n";
          @ob_flush();
          @flush();
          break;
        }

        echo "event: status\n";
        echo 'data: ' . json_encode([
          'id' => $fresh->id,
          'status' => $fresh->status,
          'finished_at' => optional($fresh->finished_at)->toIso8601String(),
          'error_message' => $fresh->error_message,
        ]) . "\n\n";
        @ob_flush();
        @flush();

        if (in_array($fresh->status, ['done', 'failed'], true)) {
          break;
        }

        if ((time() - $start) >= $timeoutSeconds) {
          // client may reconnect if needed, but we won't auto-poll in UI
          break;
        }

        sleep(1);
      }
    }, 200, [
      'Content-Type' => 'text/event-stream',
      'Cache-Control' => 'no-cache',
      'Connection' => 'keep-alive',
      'X-Accel-Buffering' => 'no', // nginx buffering off
    ]);
  }
}
