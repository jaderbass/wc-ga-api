# 🛠️ README-dev – GeoAlpin (Laravel 12 / Filament 3.2)

Interne Entwickler-Dokumentation für Aufbau, Konventionen, Namespaces und
DB-Struktur des GeoAlpin-Projekts.

Stand: 2025-10-17

---

## 1) Projektstruktur (vereinfachter Überblick)

```
app/
  Console/
    Commands/                 # Artisan Commands (Woo Sync, Tools, Healthchecks)
  Filament/
    Pages/                    # Dashboard
    Resources/
      ProductResource/
        Actions/              # BulkActions: SyncProducts, SyncVariations
        Pages/                # List/Edit/Create
        RelationManagers/     # Variants Relation
      ManufacturerResource/
      ShopResource/
      UserResource/
    Widgets/                  # KPIs/Recent
  Helpers/                    # CSV/XML Sanitizer, HeaderMapper
  Http/
    Controllers/              # Webhooks, API
  Importers/                  # GenericCsvProductImporter (+ spätere XML-Importer)
  Models/                     # Product, ProductVariation, Manufacturer, Shop, User ...
  Services/
    Export/                   # (Alt/Legacy) Exporter
    Woo/                      # Orchestrator, WooClient, Product/Variation Services
config/
  import_mappings/            # Hersteller-Mappings (CSV/XML → interne Felder)
  woo.php                     # Woo-Basisconfig
  woo_policy.php              # Sync-Policies/Heuristiken
database/
  migrations/                 # Tabellen & Indizes
routes/
  web.php
  api.php                     # Webhooks (Woo), ggf. API-Endpoints
tests/
  bin/                        # Hilfsskripte (optional → in Commands migrieren)
```

---

## 2) Namespaces & zentrale Klassen

| Zweck | Klasse / Namespace | Notizen |
|------|---------------------|---------|
| Orchestrator | `App\Services\Woo\ProductExportOrchestrator` | Single Entry für Parent- & Varianten-Sync (`syncSingle`, `syncVariationsForProduct`) |
| Parent Upsert | `App\Services\Woo\ProductUpsertService` | erstellt/aktualisiert Woo `/products` |
| Varianten Sync | `App\Services\Woo\VariationSyncService` | erstellt/aktualisiert `/products/{id}/variations` |
| HTTP-Client | `App\Services\Woo\WooClient` | Auth, Transport, Fehlerhandling |
| Produkt-Service | `App\Services\Woo\WooProductService` | Payload-Build, Hashing, Calls |
| Lookup | `App\Services\Woo\WooProductLookupService` | Sucht Woo-IDs/Slugs/SKUs |
| Attribute | `App\Services\Woo\WooAttributeResolver` | Farb-/Größen-Attribute |
| Filament BulkActions | `App\Filament\Resources\ProductResource\Actions\*` | `SyncProductsBulkAction`, `SyncVariationsBulkAction` |

---

## 3) Datenbank-Schema (Kurzüberblick)

> Hinweis: Konkrete Felder können je nach Migration variieren.

### `products`
- `id` PK
- `manufacturer_id` FK
- `product_number` (interne/Hersteller-Ref)
- `name`, `description`
- `product_type` ENUM(`simple`,`variable`)
- `woo_product_id` (nullable) – gesetzt nach erstem Sync
- `status`, `created_at`, `updated_at`
- Indizes: auf häufige Filter/Lookup-Felder

### `product_variations`
- `id` PK
- `product_id` FK → `products`
- `sku`, `ean`, `color`, `size`, `weight` ...
- `woo_variation_id` (nullable)
- `sync_hash` (optional, zur Change-Erkennung)
- Indizes: `sku`, `ean`, `product_id`

### `manufacturers`, `shops`, `users`
- `shops`: Woo-Base-URL, Keys, `is_default`

---

## 4) Migrationen & Baselines

- Neue Tabellen/Spalten **klar benennen**, z. B. `add_woo_fields_to_product_variations`.
- Nach umfangreichen Migrationen: `php artisan schema:dump --prune` (Baseline aktualisieren).
- Alte „Update/Remove“-Migrations gelegentlich in Baseline konsolidieren (Repo-Historie beachten).

---

## 5) ENV-Keys (Woo & System)

```
WOO_API_BASE_URL=
WOO_API_KEY=
WOO_API_SECRET=

LOG_LEVEL=debug
APP_ENV=local
APP_URL=http://127.0.0.1:8000
```

Optional/Projekt:
```
WOO_SYNC_ENABLED=true
WOO_TIMEOUT=30
WOO_RETRY=2
```

Konfiguration/Defaults in `config/woo.php` und Heuristiken in `config/woo_policy.php` dokumentieren.

---

## 6) Sync-Flows (intern)

**Parent-Produkte:**
1. UI: `SyncProductsBulkAction` → Modal → `ProductExportOrchestrator::syncSingle($product)`
2. Orchestrator entscheidet `create` vs `update` (Preflight über Lookup + `woo_product_id`).
3. `ProductUpsertService` baut Payload → `WooClient` sendet.

**Varianten:**
1. UI: `SyncVariationsBulkAction` → Modal → `ProductExportOrchestrator::syncVariationsForProduct($product)`
2. `VariationSyncService::syncProduct($product, false)` synchronisiert alle Varianten.
3. Rückgabe normalisiert: `created/updated/skipped/errors/details`.

**CLI/Webhooks:** nutzen dieselben Services, nur ohne UI-Modal.

---

## 7) Coding Guidelines

- **PHP-CS Fixer / Pint**: `./vendor/bin/pint` vor PRs.
- **Logs**: `use Illuminate\Support\Facades\Log;` und `Log::info|debug|error` – **kein** Backslash.
- **DocBlocks**: neue/angepasste Klassen & Methoden **immer** mit PHPDoc.
- **Methodensignaturen stabil halten**; Erweiterungen optional & rückwärtskompatibel.

---

## 8) Commit-Konventionen

```
feat:     neue Funktion
fix:      Bugfix
refactor: Umbau ohne Funktionsänderung
chore:    Maintenance (Build, Config, Bumps)
docs:     Dokumentation
style:    Formatierung/CS
test:     Tests/Helper
```

Beispiele:
- `feat(filament): add SyncProductsBulkAction modal for parent upsert`
- `refactor(sync): route variant sync via orchestrator entry`

Tags:
- `pre-cleanup-YYYY-MM-DD` als Sicherheitsanker vor großen Umbauten
- Releases semver: `vMAJOR.MINOR.PATCH`

---

## 9) Tests & Tools

- **Ad-hoc Skripte** unter `tests/bin/` perspektivisch in Artisan-Commands migrieren.
- **Smoke-Tests**: `php artisan list | findstr app:woo` / `php artisan route:list | findstr webhooks`.
- **Local Woo**: Timeout/Retry in `.env` erhöhen, wenn Dev-Server träge ist.

---

## 10) Queues & Skalierung (Ausblick)

- Varianten-Sync als Job (`DispatchVariationsSyncJob`) batchen.
- Rate-Limit beachten (Woo). Backoff/Retry im `WooClient`.
- Idempotenz via `sync_hash` / Payload-Hasher.

---

## 11) Troubleshooting (erweitert)

| Problem | Ursache | Maßnahme |
|--------|---------|----------|
| 401 Unauthorized | Keys/Secret falsch | `.env` prüfen, Base-URL korrekt? |
| 404 bei Variation | Parent nicht vorhanden | Erst Parent syncen |
| 409 Konflikte | Duplicate SKU/EAN | Resolver/Mapping prüfen, SKU-Policy |
| 500 Woo | Shop-Logs prüfen | Request/Response-Logging temporär aktivieren |
| Nichts passiert | Queue/Jobs aus | `QUEUE_CONNECTION=sync` für lokal, sonst Worker starten |

---

## 12) Glossar

- **Parent**: WooCommerce-Produkt (`simple`/`variable`)
- **Variation**: Varianten-Eintrag eines `variable`-Produkts
- **Upsert**: create or update
- **Orchestrator**: zentrale Koordination von Export/Sync
- **Lookup**: Ermittlung existierender Woo-IDs (SKU/Slug/Meta)
