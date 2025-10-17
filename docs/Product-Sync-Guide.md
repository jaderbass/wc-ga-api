# 🧩 GeoAlpin Produkt- und Varianten-Synchronisation

Diese Dokumentation beschreibt den aktuellen Stand der Produkt-Synchronisierung
zwischen dem Laravel-/Filament-Backend und WooCommerce.

---

## 1️⃣ Übersicht

| Bereich | Einstiegspunkt | Beschreibung |
|----------|----------------|---------------|
| **Filament UI (Dashboard)** | Bulk-Actions in **ProductResource** | Direkter Sync einzelner oder mehrerer Produkte/Varianten mit Woo |
| **CLI / Artisan** | `php artisan app:woo-sync-product` / `php artisan app:woo-sync-variations` | Automatisierte oder geplante Sync-Läufe |
| **Webhook-Listener** | `App\Http\Controllers\WooWebhookController` | Empfängt Änderungen aus Woo (z. B. product.updated) |

---

## 2️⃣ Filament UI-Sync

### Aktionen
- **Produkt synchronisieren**  
  → nutzt `SyncProductsBulkAction`  
  → ruft `ProductExportOrchestrator::syncSingle()` auf  
  → aktualisiert oder erstellt Parent-Produkte in WooCommerce

- **Varianten synchronisieren**  
  → nutzt `SyncVariationsBulkAction`  
  → ruft `ProductExportOrchestrator::syncVariationsForProduct()` auf  
  → synchronisiert alle Varianten eines Produkts mit WooCommerce

### Bedienung
1. In der **Produktliste** (Filament-Admin) gewünschte Produkte markieren.  
2. Über **Mehrfach-Operationen → Varianten synchronisieren** oder **Produkte synchronisieren** starten.  
3. Im erscheinenden Modal:
   - Shop auswählen (Default ist voreingestellt)
   - Optional „Nur geänderte senden“ / „Dry-run (nur Vorschau)“ aktivieren
4. Ergebnis-Benachrichtigung (Toast) zeigt Erfolgs- und Fehlerzähler.

![Placeholder Screenshot – Modal](docs/img/modal-sync-products.png)

![Placeholder Screenshot – Notification](docs/img/toast-sync-result.png)

---

## 3️⃣ CLI-Sync

Die Konsolen-Kommandos sind für geplante oder Massen-Synchronisationen gedacht
und verwenden intern dieselben Services wie die Filament-BulkActions.

```bash
# Alle Parent-Produkte synchronisieren
php artisan app:woo-sync-product

# Alle Varianten synchronisieren
php artisan app:woo-sync-variations
```

Optionale Parameter (sofern implementiert):

| Option | Beschreibung |
|---------|--------------|
| `--dry` | Keine Schreiboperationen (nur Vorschau) |
| `--only-changed` | Nur geänderte Datensätze synchronisieren |
| `--shop=ID` | Spezifischen Shop synchronisieren |

---

## 4️⃣ Webhook-Sync (Inbound)

WooCommerce-seitige Änderungen (z. B. Preis- oder Bestandsänderungen)
werden über Webhooks an folgende Endpoint-Route gesendet:

```
POST /api/webhooks/woo/{shopId}
```

Header:

```
X-WC-Webhook-Topic: product.updated
X-WC-Webhook-Signature: <HMAC>
```

Verarbeitung erfolgt in  
`App\Http\Controllers\WooWebhookController::handle()`  
→ dieser Controller triggert intern den Orchestrator-Sync.

---

## 5️⃣ Logging & Monitoring

- Alle Sync-Prozesse loggen nach `storage/logs/laravel.log`
- Log-Prefixe:
  - `[SyncProductsBulkAction]`
  - `[SyncVariationsBulkAction]`
  - `[ProductExportOrchestrator]`
- Filament zeigt Toast-Benachrichtigungen (Erfolg/Fehler)
- Bei Bedarf können zusätzliche Logs via `Log::debug()` aktiviert werden

---

## 6️⃣ Troubleshooting

| Symptom | Mögliche Ursache | Lösung |
|----------|------------------|--------|
| Varianten werden nicht erstellt | `woo_product_id` fehlt im Parent-Datensatz | Parent zuerst synchronisieren |
| 400/401-Fehler von Woo | API-Zugangsdaten in `.env` prüfen (`WOO_API_BASE_URL`, `WOO_API_KEY`, `WOO_API_SECRET`) | `.env` korrigieren, `php artisan optimize:clear` |
| Toast zeigt „0 Produkte synchronisiert“ | Filter „Nur geänderte senden“ aktiv | Deaktivieren oder Änderung durchführen |
| Fehlermeldung „connection refused“ | WooCommerce nicht erreichbar | Server- oder Netzwerk-Check |

---

## 7️⃣ Kommende Verbesserungen

- Optionaler Queue-Mode (`DispatchVariationsSyncJob`)
- Automatischer Dry-Run-Report per Mail (abschaltbar)
- Erweiterte Fehler-Statistik im Admin-Dashboard

---

© JAderBass web’n’more · Stand: 2025-10-17
