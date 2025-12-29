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
<script>
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
          window.location.reload();
        }
      });

      es.addEventListener('error', () => {
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
