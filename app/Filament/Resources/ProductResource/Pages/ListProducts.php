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
    <strong>Import läuft…</strong><br>
    Bitte nicht neu laden
  </div>
</div>

<script>
    function showImportOverlay() {
        const el = document.getElementById('ga-import-overlay');
        if (!el) return;

        // Dark Mode erkennen (Filament setzt meist .dark am <html>)
        if (document.documentElement.classList.contains('dark')) {
            el.classList.add('dark');
        }

        el.style.display = 'flex';
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

                showImportOverlay();

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
