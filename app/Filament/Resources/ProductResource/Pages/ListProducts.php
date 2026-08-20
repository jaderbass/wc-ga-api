<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Registert den SSE/Livewire-Listener für "Import fertig" auf der Produktliste.
     * Ziel: Nach Abschluss eines Hintergrund-Imports genau EINEN Reload auslösen.
     */
    public function mount(): void
    {
        parent::mount();

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn() => <<<'HTML'
<style>
  #ga-import-overlay {
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
  }

  #ga-import-overlay.dark {
    background: rgba(15, 23, 42, 0.85); /* dark mode */
  }

  .ga-import-box {
    text-align: center;
    font-size: 1rem;
    color: #111827;
  }

  .dark .ga-import-box {
    color: #e5e7eb;
  }

  .ga-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #d1d5db;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: ga-spin 1s linear infinite;
    margin: 0 auto 1rem;
  }

  @keyframes ga-spin {
    to { transform: rotate(360deg); }
  }
</style>

<div id="ga-import-overlay">
  <div class="ga-import-box">
    <div class="ga-spinner"></div>

    <strong id="ga-overlay-title">Import läuft…</strong><br>

    <div id="ga-overlay-status">
      Bitte nicht neu laden
    </div>

    <div id="ga-overlay-progress" style="display: none; margin-top: 0.75rem;">
      <div id="ga-overlay-progress-text"></div>
      <div id="ga-overlay-result-text"></div>
    </div>
  </div>
</div>

<script>
    function showImportOverlay(title = 'Import läuft…') {
        const el = document.getElementById('ga-import-overlay');
        if (!el) return;

        if (document.documentElement.classList.contains('dark')) {
            el.classList.add('dark');
        }

        const titleEl = document.getElementById('ga-overlay-title');
        const statusEl = document.getElementById('ga-overlay-status');
        const progressEl = document.getElementById('ga-overlay-progress');

        if (titleEl) {
            titleEl.textContent = title;
        }

        if (statusEl) {
            statusEl.textContent = 'Bitte nicht neu laden';
        }

        if (progressEl) {
            progressEl.style.display = 'none';
        }

        el.style.display = 'flex';
    }

    function updatePetzlSyncOverlay(data) {
        const statusEl = document.getElementById('ga-overlay-status');
        const progressEl = document.getElementById('ga-overlay-progress');
        const progressTextEl = document.getElementById('ga-overlay-progress-text');
        const resultTextEl = document.getElementById('ga-overlay-result-text');

        if (!progressEl || !progressTextEl || !resultTextEl) {
            return;
        }

        progressEl.style.display = 'block';

        progressTextEl.textContent =
            `${data.completed ?? 0} von ${data.total ?? 0} verarbeitet · ${data.progress ?? 0} %`;

        resultTextEl.textContent =
            `${data.successful ?? 0} erfolgreich · ${data.failed ?? 0} fehlgeschlagen`;

        if (statusEl) {
            statusEl.textContent =
                data.status === 'running'
                    ? 'Synchronisierung läuft'
                    : 'Bitte nicht neu laden';
        }
    }

    function hideImportOverlay() {
        const el = document.getElementById('ga-import-overlay');
        if (!el) return;
        el.style.display = 'none';
    }


    (function () {
        // Debug: zeigt dir, ob dieses Script überhaupt auf der Seite ankommt
        console.log('[import-ui] listener script loaded');

        function bind() {
            if (typeof Livewire === 'undefined') {
                console.warn('[import-ui] Livewire not available');
                return;
            }

            // nur einmal binden
            if (window.__gaImportSseProductsBound) return;
            window.__gaImportSseProductsBound = true;

            Livewire.on('import-run-started', (payload) => {
                console.log('[import-ui] import-run-started', payload);

                showImportOverlay('Import läuft…');

                const runId = payload?.runId;
                if (!runId) return;

                // alte Verbindung schließen
                if (window.__gaImportEventSource) {
                    window.__gaImportEventSource.close();
                }

                const url = `/imports/runs/${runId}/events`;
                const es = new EventSource(url);
                window.__gaImportEventSource = es;

                es.addEventListener('status', (ev) => {
                    const data = JSON.parse(ev.data || '{}');
                    if (!data.status) return;

                    if (data.status === 'done' || data.status === 'failed') {
                        es.close();
                        hideImportOverlay();
                        window.location.reload();
                    }
                });

                es.addEventListener('error', () => {
                    hideImportOverlay();
                    es.close();
                });
            });

            Livewire.on('petzl-description-sync-started', (payload) => {
                console.log('[petzl-sync-ui] started', payload);

                showImportOverlay('Petzl-Beschreibungen werden synchronisiert…');

                updatePetzlSyncOverlay({
                    status: 'queued',
                    total: 0,
                    completed: 0,
                    successful: 0,
                    failed: 0,
                    progress: 0,
                });

                const runId = payload?.runId;
                if (!runId) return;

                if (window.__gaPetzlSyncEventSource) {
                    window.__gaPetzlSyncEventSource.close();
                }

                const url = `/petzl-description-sync/runs/${runId}/events`;
                const es = new EventSource(url);

                window.__gaPetzlSyncEventSource = es;

                es.addEventListener('status', (ev) => {
                    const data = JSON.parse(ev.data || '{}');

                    console.log('[petzl-sync-ui] status', data);

                    if (!data.status) return;

                    updatePetzlSyncOverlay(data);

                    if (data.status === 'done' || data.status === 'failed') {
                        es.close();

                        setTimeout(() => {
                            hideImportOverlay();
                            window.location.reload();
                        }, 4000);
                    }
                });

                es.addEventListener('error', () => {
                    console.warn('[petzl-sync-ui] SSE connection interrupted');
                });
            });
        }

        // Wenn Livewire schon da ist: sofort binden. Wenn nicht: beim init binden.
        if (typeof Livewire !== 'undefined') {
            bind();
        } else {
            document.addEventListener('livewire:init', bind, { once: true });
        }
    })();
</script>
HTML
        );
    }
}
