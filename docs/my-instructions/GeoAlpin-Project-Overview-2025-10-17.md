# GeoAlpin – Projektübersicht & aktueller Stand


**Analyse-Datum:** 2025-10-17


## Stack & Abhängigkeiten

- Laravel: `^12.0`

- Filament: `^3.2`

- PHP: `^8.2`

- Wichtige Pakete: `doctrine/dbal` ^4.3, `guzzlehttp/guzzle` ^7.9, `laravel/tinker` ^2.10.1, `league/csv` ^9.25, `spatie/laravel-permission` ^6.21


## Projektstruktur (Kurz)

- Gesamtdateien: **298**, davon PHP: **240**, Config: **19**

- Wichtige Verzeichnisse:

  - `app/Filament` ✔️

  - `app/Services/Woo` ✔️

  - `app/Importers` ✔️

  - `app/Console/Commands` ✔️

  - `config` ✔️

  - `database/migrations` ✔️

  - `routes` ✔️



## Filament-Ressourcen & Widgets

- **Resources**:
  - `app/Filament/Resources/ManufacturerResource.php`
  - `app/Filament/Resources/ProductResource.php`
  - `app/Filament/Resources/ShopResource.php`
  - `app/Filament/Resources/UserResource.php`
  - `app/Filament/Resources/ManufacturerResource/Pages/CreateManufacturer.php`
  - `app/Filament/Resources/ManufacturerResource/Pages/EditManufacturer.php`
  - `app/Filament/Resources/ManufacturerResource/Pages/ListManufacturers.php`
  - `app/Filament/Resources/ManufacturerResource/RelationManagers/ManufacturerAuditRelationManager.php`
  - `app/Filament/Resources/ProductResource/Actions/SyncVariationsBulkAction.php`
  - `app/Filament/Resources/ProductResource/Pages/CreateProduct.php`
  - `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
  - `app/Filament/Resources/ProductResource/Pages/ListProducts.php`
  - `app/Filament/Resources/ProductResource/RelationManagers/ProductVariantRelationManager.php`
  - `app/Filament/Resources/ShopResource/Pages/CreateShop.php`
  - `app/Filament/Resources/ShopResource/Pages/EditShop.php`
  - `app/Filament/Resources/ShopResource/Pages/ListShops.php`
  - `app/Filament/Resources/UserResource/Pages/CreateUser.php`
  - `app/Filament/Resources/UserResource/Pages/EditUser.php`
  - `app/Filament/Resources/UserResource/Pages/ListUsers.php`


- **Widgets**:
  - `app/Filament/Widgets/ManufacturerCountWidget.php`
  - `app/Filament/Widgets/ProductCountWidget.php`
  - `app/Filament/Widgets/RecentProductsWidget.php`
  - `app/Filament/Widgets/WelcomeWidget.php`


## Wichtige Domänen-Services (Woo)

- `app/Console/Commands/WooExportSample.php`
- `app/Console/Commands/WooPingCommand.php`
- `app/Console/Commands/WooResetMappingsCommand.php`
- `app/Console/Commands/WooShopTest.php`
- `app/Console/Commands/WooSyncProduct.php`
- `app/Console/Commands/WooSyncProductCommand.php`
- `app/Console/Commands/WooSyncVariationsCommand.php`
- `app/Http/Controllers/WooWebhookController.php`
- `app/Services/Export/WooCommerceExporter.php`
- `app/Services/Woo/ProductExportOrchestrator.php`
- `app/Services/Woo/ProductUpsertService.php`
- `app/Services/Woo/VariationPayloadBuilder.php`
- `app/Services/Woo/VariationSyncService.php`
- `app/Services/Woo/WooAttributeResolver.php`
- `app/Services/Woo/WooClient.php`
- `app/Services/Woo/WooProductLookupService.php`
- `app/Services/Woo/WooProductService.php`


## Auffälligkeiten / Inkonsistenzen

- Es existiert `SyncVariationsBulkAction`, **kein** Pendant für Parent-Produkte (`SyncProductsBulkAction`) → _Vorschlag: ergänzen und Orchestrator konsequent nutzen_.

- `ProductExportOrchestrator` + `ProductUpsertService` + `VariationSyncService` existieren, aber Aufrufpfade sind verteilt (Console Commands, Filament-BulkAction). Konsolidierung empfohlen.

- Mehrere Woo-Console-Commands (`WooSyncProduct`, `WooSyncProductCommand`, `WooSyncVariationsCommand`) – prüfen, ob Redundanz vorliegt.

- Potenziell ungenutzte Klassen (nur Eigennennung gefunden): siehe Cleanup-Plan.


## Tests & Hilfs-Skripte

- `tests/bin/*.php` beinhaltet praktische Helferskripte (SKU Lookup etc.). Für Produktion trennen oder in `artisan`-Kommandos migrieren.
