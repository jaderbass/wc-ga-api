# Woo Resolver Package (Parent/Variation, SKU-Policy)

## Installation (Kurz)
1. Dateien in euer Projekt kopieren.
2. `php artisan migrate`
3. `.env` prüfen:
   - `WOO_ALLOW_SKU_WRITE_BACK=false`
   - `WOO_VARIANT_REQUIRES_SKU=true`
   - `WOO_PARENT_MUST_NOT_HAVE_SKU=true`
4. Abhängigkeiten binden (Service Provider oder Container-Bindings):
   - `WooParentResolverInterface` → `WooParentResolver`

## Orchestrator
Siehe `app/Woo/Orchestrator/ProductExportOrchestrator.php` – Resolver wird vor jedem Upsert aufgerufen.

## Reports
`php artisan woo:sync-reports` erzeugt CSVs (siehe Command).
