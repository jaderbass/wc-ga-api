# GeoAlpin – Cleanup & Refactor Plan


## Ziele
- Einheitliche Bulk-Actions für **Produkte** und **Varianten**
- Single Orchestrator Entry-Points (UI & CLI)
- Tote/Redundante Klassen eliminieren
- Logging vereinheitlichen (kein `\Log`, Namespace via `use Illuminate\Support\Facades\Log;` und Aufrufe `Log::...`)


## Konkrete Maßnahmen

1) **Neue BulkAction:** `ProductResource/Actions/SyncProductsBulkAction.php`
   - Ruft `ProductExportOrchestrator` für ausgewählte **Parent-Produkte** auf.
   - Spiegelt Aufbau von `SyncVariationsBulkAction` (Permissions, Feedback, Queues).


2) **Filament Resource aufräumen** (`ProductResource.php`)
   - Actions gruppieren: _Sync Parent_, _Sync Variants_, _Reset Mapping_, _Export Sample_.
   - DocBlocks ergänzen.


3) **Console Commands konsolidieren**
   - Behalten: `WooSyncProductCommand`, `WooSyncVariationsCommand`.
   - Entfernen/verschmelzen: `WooSyncProduct` (ohne `Command`?), `WooExportSample` ggf. in `artisan`-Option.


4) **Service-Layer Schnittstellen**
   - `ProductExportOrchestrator` als einziger Einstieg; `ProductUpsertService` / `VariationSyncService` als interne Abhängigkeiten.
   - `WooProductService` nur HTTP-nahe Aufgaben (Payload build via `VariationPayloadBuilder`, Lookup via `WooProductLookupService`).


## Potenziell ungenutzte Klassen (bitte manuell prüfen)

| Class | FQCN | File |
|---|---|---|
| MakeAdminUser | App\Console\Commands\MakeAdminUser | `app/Console/Commands/MakeAdminUser.php` |
| TunnelQuick | App\Console\Commands\TunnelQuick | `app/Console/Commands/TunnelQuick.php` |
| WooSyncProduct | App\Console\Commands\WooSyncProduct | `app/Console/Commands/WooSyncProduct.php` |
| XmlValueSanitizer | App\Helpers\XmlValueSanitizer | `app/Helpers/XmlValueSanitizer.php` |
| PingController | App\Http\Controllers\PingController | `app/Http/Controllers/PingController.php` |
| BaseImporter | App\Imports\BaseImporter | `app/Imports/BaseImporter.php` |
| BaseXmlImporter | App\Imports\BaseXmlImporter | `app/Imports/BaseXmlImporter.php` |
| ImporterForAliens | App\Imports\Manufacturer\ImporterForAliens | `app/Imports/Manufacturer/ImporterForAliens.php` |
| ImporterForKask | App\Imports\Manufacturer\ImporterForKask | `app/Imports/Manufacturer/ImporterForKask.php` |
| ImporterForKratos | App\Imports\Manufacturer\ImporterForKratos | `app/Imports/Manufacturer/ImporterForKratos.php` |
| ImporterForSingingRock | App\Imports\Manufacturer\ImporterForSingingRock | `app/Imports/Manufacturer/ImporterForSingingrock.php` |


## Commit-Vorschläge (Sequenz)

```bash
# 1) Neue BulkAction für Parent-Produkte
git add app/Filament/Resources/ProductResource/Actions/SyncProductsBulkAction.php
git commit -m "feat(filament): add SyncProductsBulkAction to align with variations; unify export entry point"

# 2) ProductResource Actions gruppieren & DocBlocks
git add app/Filament/Resources/ProductResource.php
git commit -m "refactor(filament): group product actions; add docblocks; wire up parent+variant sync"

# 3) Konsolidierung Console Commands
git rm app/Console/Commands/WooSyncProduct.php
git commit -m "chore(cli): remove legacy WooSyncProduct; keep WooSyncProductCommand + WooSyncVariationsCommand"

# 4) Logging vereinheitlichen
git add -A
git commit -m "style(logging): import Log facade and remove backslashes across services and actions"
```

## Weitere Hinweise
- Tests/Helper-Skripte unter `tests/bin` als `artisan`-Kommandos neu implementieren (optional).
- `.env` Variablen für Woo klar dokumentieren (`config/woo.php`, `config/woo_policy.php`).
- Manufacturer-spezifische Mappings in `config/import_mappings/*.php` konsistent halten (Naming, Fallbacks, NULL vs "N/A").
