## 🔄 Produkt- & Varianten-Synchronisation

Das System unterstützt den bidirektionalen Sync von Produkten und Varianten
zwischen dem Laravel/Filament-Backend und WooCommerce.

---

### 1️⃣ Sync im Filament-Dashboard

- **Produkt synchronisieren** → nutzt `SyncProductsBulkAction`  
  → Orchestrator: `ProductExportOrchestrator::syncSingle()`

- **Varianten synchronisieren** → nutzt `SyncVariationsBulkAction`  
  → Orchestrator: `ProductExportOrchestrator::syncVariationsForProduct()`

**Bedienung:**
1. In der Produktliste gewünschte Einträge markieren.  
2. Menü **Mehrfach-Operationen → Produkt/Varianten synchronisieren** wählen.  
3. Im Modal Shop, „Nur geänderte senden“ oder „Dry-run“ auswählen.  
4. Nach Abschluss erscheinen Erfolg/Fehler-Meldungen als Toast.

---

### 2️⃣ CLI-Sync (Artisan)

Für automatisierte Abläufe oder Tests können alle Produkte/Varianten
auch über Artisan synchronisiert werden:

```bash
# Parent-Produkte
php artisan app:woo-sync-product

# Varianten
php artisan app:woo-sync-variations
```

Optionale Parameter (wenn implementiert):

| Option | Beschreibung |
|---------|--------------|
| `--dry` | Nur Simulation, keine Schreiboperationen |
| `--only-changed` | Nur geänderte Datensätze synchronisieren |
| `--shop=ID` | Zielshop wählen |

---

### 3️⃣ Logs & Fehleranalyse

- Logs unter `storage/logs/laravel.log`
- Prefixe:
  - `[SyncProductsBulkAction]`
  - `[SyncVariationsBulkAction]`
  - `[ProductExportOrchestrator]`
- Filament zeigt Statusmeldungen per Toast an.
- Bei Problemen: `.env`-Einträge für Woo prüfen (`WOO_API_BASE_URL`, `WOO_API_KEY`, `WOO_API_SECRET`).

---

📘 **Vollständige Dokumentation:**  
siehe [`docs/Product-Sync-Guide.md`](docs/Product-Sync-Guide.md)
