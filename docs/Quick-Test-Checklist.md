# ✅ Quick Test Checklist – Woo Product Sync (GeoAlpin)

Kurz & knapp für den schnellen Abend-Check.

---

## 1) Artisan Smoke-Test (Parent-Produkte)

**Invalid-ID-Recovery prüfen:** setzt absichtlich eine falsche Woo-ID und erwartet „Create“-Fallback.

```bash
php artisan app:smoke-woo-product --product=8 --invalidate
```

**Create erzwingen:** setzt `woo_product_id` auf NULL und erwartet POST (Create).

```bash
php artisan app:smoke-woo-product --product=8 --nullify
```

**Alte ID wiederherstellen (falls nötig):**
```bash
php artisan app:smoke-woo-product --product=8 --restore=13162
```

**Erwartung (Auszug):**
```json
{
  "product_id": 8,
  "action": "created" | "updated",
  "remote_id": 13162,
  "current_woo_id": 13162
}
```

---

## 2) Filament UI (Bulk-Actions)

1. Produkte auswählen → **Mehrfach-Operationen → Produkt synchronisieren**
2. Modal: Shop wählen, optional „Nur geänderte senden“ / „Dry-run“
3. Toast beachten: **OK / Übersprungen / Fehler** + ggf. Woo-ID im Detail

---

## 3) Logs & Fehler

- Logfile: `storage/logs/laravel.log`
- Suche nach: `Orchestrator:` und `SmokeWooProductSync`
- Typische Ursachen:
  - `woocommerce_rest_product_invalid_id` → sollte jetzt **automatisch** Create-Retry auslösen
  - 401/403 → Woo-Credentials `.env` prüfen
  - 404 → Parent in Woo fehlt (vorher Create)

---

## 4) Wenn etwas schiefgeht

- Einmal **Invalid-ID-Test** erneut ausführen (siehe 1) – prüft den Fallback-Mechanismus isoliert.
- Response/Body aus `ProductUpsertService` kurz im Log prüfen (Status + `body.id`).
- Ggf. temporär `--fail-hard` am Smoke-Test setzen, um die Exception direkt zu sehen:

```bash
php artisan app:smoke-woo-product --product=8 --invalidate --fail-hard
```

---

## 5) Optional: Sicherungs-Tag erstellen

```bash
git tag v0.4.2-pre-stable
git push origin v0.4.2-pre-stable
```

Gute Nacht & viel Erfolg beim schnellen Check! 🌙
