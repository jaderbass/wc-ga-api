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

    public function mount(): void
    {
        parent::mount();

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn() => <<<'HTML'
<!-- dein JS snippet hier -->
 <script>
document.addEventListener('livewire:init', () => {
  // nur einmal registrieren
  if (window.__gaImportSseProductsBound) return;
  window.__gaImportSseProductsBound = true;

  Livewire.on('import-run-started', (payload) => {
    const runId = payload?.runId;
    if (!runId) return;

    // falls eine alte Verbindung offen ist -> schließen
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
        window.location.reload(); // genau EINMAL
      }
    });

    es.addEventListener('error', () => {
      es.close();
    });
  });
});
</script>
HTML
        );
    }
}
