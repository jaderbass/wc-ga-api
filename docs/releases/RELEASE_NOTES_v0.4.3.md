# 🏷️ v0.4.3 – Stable Woo Export/Sync Baseline  

**Datum:** 2025-10-22  
**Branch:** `feature/woo-export`  
**Tag:** `v0.4.3`  

---

## 🚀 Überblick

Nach intensiver Entwicklungs- und Testphase ist die WooCommerce-Anbindung jetzt stabil.  
Eltern- und Variantenprodukte werden zuverlässig synchronisiert, Attribute sauber gemappt,  
und die Import-/Export-Pipeline erreicht damit erstmals einen voll funktionsfähigen Zustand.

Dies ist der erste „**stabile Baseline-Stand**“ für alle weiteren API- und Performance-Optimierungen.

---

## ✨ Neue Funktionen & Verbesserungen

### 🧩 WooCommerce-Synchronisierung

- **Parent-Sync:** legt Hauptprodukte korrekt als `variable` an und aktualisiert bestehende.
- **Varianten-Sync:** erzeugt oder aktualisiert Variationen zuverlässig mit korrekten Attribut-Zuweisungen.
- **Name-Resolver:** bezieht Produktnamen direkt aus der Datenbank (`products.product_name`);  
  keine Fallback-Titel („Product #ID“) mehr im Woo-Shop.
- **Attribute-Aggregation:** automatischer Aufbau aus `pv → piv → pav → pa` inklusive `pa_*`-Slug-Normalisierung.
- **Stock-Status-Mapping:** vereinheitlicht auf `instock|outofstock|onbackorder`.

### ⚙️ Datenkonsistenz & Guards

- **Timestamp-Guard:** sendet nur geänderte Produkte (`updated_at > last_synced_at`).
- **Whitelisting:** nur explizit ausgewählte Produkt-IDs werden synchronisiert (Filament-Bug-Absicherung).
- **Model-Refresh:** `Product::refresh()` sorgt für aktuelle DB-Werte in jeder Bulk-Action.
- **Response-Normalisierung:** alle Woo-Responses werden in ein einheitliches Schema überführt (`status`, `remote_id`, `action`, `body`).

### 🪵 Logging & Debugging

- Präzise Debug-Logs mit klarer Herkunftsangabe (`source: db:product_name`, `resolved name`, `payload`).
- Erfolgreiche Syncs: `INFO`-Level mit Remote-ID.
- Übersprungene oder fehlerhafte Produkte: `WARNING`-/`ERROR`-Level mit eindeutiger Ursache.

---

## 🧱 Struktur-Anpassungen

- Neue Guard-Logik in `SyncProductsBulkAction` (inkl. Timestamp- und Whitelist-Prüfung).  
- `ProductUpsertService`: konsolidierte HTTP-Pipelines (`updateExisting()`/`createNew()` nutzen Laravel-HTTP).  
- Migration-Feld bestätigt: `products.last_synced_at` (kein `woo_synced_at` mehr).  

---

## 🧪 Test-Status

✔️ Parent-Produkte erfolgreich angelegt  
✔️ Varianten korrekt zugeordnet  
✔️ Nur geänderte Produkte gesendet  
✔️ Keine unbeabsichtigten Updates fremder Produkte  

---

## 🪜 Nächste Schritte

- [ ] Orchestrator-Feinschliff (einheitliches `action`-Handling).  
- [ ] Optionale Attribut-Slug-Vereinheitlichung auf globale Woo-Attribute (`pa_*`).  
- [ ] Performance-Profiling für Bulk-Sync großer Produktmengen.  
- [ ] Optionaler Queue-/Job-Modus für asynchrone Verarbeitung.  

---

**Autor:** Jörg Aderhold – JAderBass web’n’more  
**Projekt:** GeoAlpin (Laravel 12 + Filament 3 + WooCommerce API)  
