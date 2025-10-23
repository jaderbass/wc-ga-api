<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Startet einen Cloudflare Quick Tunnel (cloudflared tunnel --url http://host:port)
 * und gibt die generierte trycloudflare.com-URL aus (inkl. Copy-to-Clipboard).
 *
 * Hinweise:
 * - Läuft im Vordergrund (wie cloudflared selbst). Lass das Terminal geöffnet.
 * - Nutze --bin, wenn cloudflared nicht im PATH liegt.
 */
class TunnelQuick extends Command
{
    protected $signature = 'tunnel:quick
    {--host=127.0.0.1 : Local host}
    {--port=8000 : Local port}
    {--path=/api/webhooks/woo/ping : Optionaler Test-Pfad}
    {--bin= : Voller Pfad zu cloudflared(.exe) (optional)}
';


    protected $description = 'Startet einen Cloudflare Quick Tunnel und zeigt die öffentliche URL an.';

    public function handle(): int
    {
        $host     = (string) $this->option('host');
        $port     = (string) $this->option('port');
        $testPath = (string) $this->option('path');

        // 1) Binärpfad ermitteln (Option → .env → Fallback)
        $bin = (string) ($this->option('bin') ?: env('CLOUDFLARED_BIN', 'cloudflared'));

        // 2) Falls absoluter Pfad angegeben, aber Datei existiert nicht → auf PATH-Fallback schalten
        if (!is_file($bin) && $bin !== 'cloudflared') {
            $this->warn("Hinweis: '{$bin}' nicht gefunden, versuche 'cloudflared' aus PATH …");
            $bin = 'cloudflared';
        }

        // 3) Verfügbarkeit prüfen
        if (! $this->binaryAvailable($bin)) {
            $this->error("cloudflared nicht gefunden. Entweder in PATH legen oder --bin/CLOUDFLARED_BIN verwenden.");
            return self::FAILURE;
        }

        // 4) Erst jetzt URL & Prozess vorbereiten
        $local = "http://{$host}:{$port}";
        $this->info("Starte Cloudflare Quick Tunnel für {$local} … (Strg+C zum Beenden)");

        $cmd = [$bin, 'tunnel', '--url', $local];

        $process = new Process($cmd);
        $process->setTimeout(null);

        $foundUrl = null;

        // Stream-Callback: stdout/stderr analysieren
        $process->start(function (string $type, string $buffer) use (&$foundUrl, $testPath) {
            // z. B. "https://xyz.trycloudflare.com"
            if (!$foundUrl && preg_match('#https://[a-z0-9-]+\.trycloudflare\.com#i', $buffer, $m)) {
                $foundUrl = $m[0];

                $this->newLine();
                $this->components->twoColumnDetail('Public URL', $foundUrl);

                // Test-URL (z. B. Ping-Route)
                $test = rtrim($foundUrl, '/') . $testPath;
                $this->components->twoColumnDetail('Test-Ping', $test);

                // In Zwischenablage kopieren (Windows `clip`)
                $this->copyToClipboard($foundUrl);

                $this->newLine();
                $this->line('Hinweis: Tunnel läuft im Vordergrund. Dieses Fenster geöffnet lassen.');
                $this->newLine();
            }

            // Optional: mit -v Logs sehen
            if ($this->output->isVerbose()) {
                $this->output->write($buffer);
            }
        });

        // Offen halten, bis abgebrochen
        while ($process->isRunning()) {
            usleep(200000);
        }

        return $process->getExitCode() === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function binaryAvailable(string $bin): bool
    {
        try {
            $p = new Process([$bin, '--version']);
            $p->setTimeout(5);
            $p->run();
            return $p->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function copyToClipboard(string $text): void
    {
        try {
            // Windows: clip
            if (PHP_OS_FAMILY === 'Windows') {
                $p = Process::fromShellCommandline('clip');
                $p->setInput($text);
                $p->run();
                if ($p->isSuccessful()) {
                    $this->components->twoColumnDetail('Clipboard', 'URL kopiert ✔');
                    return;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $this->components->twoColumnDetail('Clipboard', 'Kopieren nicht unterstützt');
    }
}
