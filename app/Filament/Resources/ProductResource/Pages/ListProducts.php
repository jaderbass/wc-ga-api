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
     * Registriert das globale Overlay für Import- und Petzl-Beschreibungssync-Vorgänge.
     */
    public function mount(): void
    {
        parent::mount();

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn () => <<<'HTML'
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
        background: rgba(15, 23, 42, 0.85);
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
        to {
        transform: rotate(360deg);
        }
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
        /**
         * Zeigt das Overlay mit dem angegebenen Titel an.
         */
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

        /**
         * Aktualisiert die Fortschrittsanzeige des Petzl-Beschreibungssyncs.
         */
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

        /**
         * Blendet das Overlay aus.
         */
        function hideImportOverlay() {
            const el = document.getElementById('ga-import-overlay');

            if (!el) return;

            el.style.display = 'none';
        }

        /**
         * Startet die SSE-Verbindung für einen Petzl-Beschreibungssync.
         */
        function startPetzlDescriptionSyncStream(runId) {
            showImportOverlay('Petzl-Beschreibungen werden synchronisiert…');

            const statusEl = document.getElementById('ga-overlay-status');

            if (statusEl) {
                statusEl.textContent = 'Synchronisierung wird vorbereitet…';
            }

            if (window.__gaPetzlSyncEventSource) {
                window.__gaPetzlSyncEventSource.close();
            }

            const url = `/petzl-description-sync/runs/${runId}/events`;
            const es = new EventSource(url);

            window.__gaPetzlSyncEventSource = es;

            es.addEventListener('status', (ev) => {
                const data = JSON.parse(ev.data || '{}');

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
        }

        (function () {
            console.log('[import-ui] listener script loaded');

            /**
             * Registriert die Livewire-Listener genau einmal.
             */
            function bind() {
                if (typeof Livewire === 'undefined') {
                    console.warn('[import-ui] Livewire not available');
                    return;
                }

                if (window.__gaImportSseProductsBound) {
                    return;
                }

                window.__gaImportSseProductsBound = true;

                Livewire.on('import-run-started', (payload) => {
                    showImportOverlay('Import läuft…');

                    const runId = payload?.runId;
                    const waitForPetzlSync =
                        payload?.waitForPetzlSync === true;

                    if (!runId) return;

                    if (window.__gaImportEventSource) {
                        window.__gaImportEventSource.close();
                    }

                    const url = waitForPetzlSync
                        ? `/imports/runs/${runId}/events?wait_for_petzl_sync=1`
                        : `/imports/runs/${runId}/events`;

                    const es = new EventSource(url);

                    window.__gaImportEventSource = es;

                    es.addEventListener('status', (ev) => {
                        const data = JSON.parse(ev.data || '{}');

                        if (!data.status) return;

                        if (data.status === 'failed') {
                            es.close();
                            hideImportOverlay();
                            window.location.reload();

                            return;
                        }

                        if (data.status !== 'done') {
                            return;
                        }

                        if (!waitForPetzlSync) {
                            es.close();
                            hideImportOverlay();
                            window.location.reload();

                            return;
                        }

                        if (data.petzl_sync_waiting) {
                            showImportOverlay(
                                'Import abgeschlossen – Petzl-Beschreibungssync wird gestartet…'
                            );

                            return;
                        }

                        if (data.petzl_sync_run_id) {
                            es.close();

                            startPetzlDescriptionSyncStream(
                                data.petzl_sync_run_id
                            );

                            return;
                        }

                        if (data.petzl_sync_unavailable) {
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
                    const runId = payload?.runId;

                    if (!runId) return;

                    startPetzlDescriptionSyncStream(runId);
                });
            }

            if (typeof Livewire !== 'undefined') {
                bind();
            } else {
                document.addEventListener(
                    'livewire:init',
                    bind,
                    { once: true }
                );
            }
        })();
    </script>
    HTML
        );
    }
}
