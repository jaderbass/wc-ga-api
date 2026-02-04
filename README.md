# 🌄 GeoAlpin – Produkt- & Varianten-Sync zwischen Laravel/Filament und WooCommerce

Dieses Projekt automatisiert den Import, die Verwaltung und den Export von Produktdaten
zwischen dem Laravel-/Filament-Backend und WooCommerce-Shops.

Entwickelt von **JAderBass web’n’more** (Jörg Aderhold)  
Stand: 2025-10-17

---

## 🧭 Projektüberblick

**Ziel:**  

- Automatische Synchronisierung von Produkt- und Variantendaten mit WooCommerce  
- Hersteller-unabhängige Import-Pipeline (CSV, XML, später API)  
- Vereinheitlichte Export- und Sync-Mechanismen  
- Zentrale Steuerung via Filament-Dashboard

**Technologien:**  

- Laravel 12 (PHP 8.3)  
- Filament 3.2  
- MySQL 8.x  
- WooCommerce REST API (v3)  
- Composer / npm / Vite

---

## ⚙️ Systemvoraussetzungen

| Komponente | Empfehlung |
| ------------- | ------------- |
| **PHP** | ≥ 8.3 mit `curl`, `mbstring`, `intl`, `xml`, `json` |
| **MySQL** | ≥ 8.0 |
| **Webserver** | Apache 2.4 oder Nginx 1.18+ |
| **Node.js** | ≥ 20 |
| **Composer** | ≥ 2.7 |
| **Speicherbedarf** | ≥ 512 MB (Dev), ≥ 2 GB (Prod) |

---

## 🚀 Installation & Setup

### 1️⃣ Repository klonen

```bash
git clone https://github.com/jaderbass/wc-ga-api.git
cd wc-ga-api
```

### 2️⃣ Abhängigkeiten installieren

```bash
composer install
npm install && npm run build
```

### 3️⃣ Env-Datei erstellen

```bash
cp .env.example .env
php artisan key:generate
```

### 4️⃣ Datenbank konfigurieren

Passe in `.env` an:

```dotenv
DB_DATABASE=geoalpin
DB_USERNAME=geoalpin_user
DB_PASSWORD=...
```

### 5️⃣ Migration & Baseline-Dump

```bash
php artisan migrate
php artisan db:seed   # falls Seeders vorhanden
php artisan schema:dump --prune
```

### 6️⃣ Filament-Admin starten

```bash
php artisan serve
```

Zugriff: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)

---

## 🧩 Import-Module

| Typ | Basisklasse | Beschreibung |
| ------ | -------------- | -------------- |
| CSV | `GenericCsvProductImporter` | Importiert Lieferanten-CSV nach Mapping-Schema |
| XML | `BaseXmlImporter` (geplant) | Liest XML-Feeds und wandelt sie in Produkt-Entitäten um |
| API | (in Planung) | Direkter API-Feed-Import über Hersteller-Endpoints |

Konfigurationen unter `config/import_mappings/*.php`  
Logik unter `app/Importers/` und `app/Helpers/`

---

## 🔄 Produkt- & Varianten-Synchronisation

Das System unterstützt den bidirektionalen Sync von Produkten und Varianten
zwischen Laravel/Filament und WooCommerce.

### Filament-Dashboard

- **Produkt synchronisieren** → `SyncProductsBulkAction` → `ProductExportOrchestrator::syncSingle()`
- **Varianten synchronisieren** → `SyncVariationsBulkAction` → `ProductExportOrchestrator::syncVariationsForProduct()`

**Bedienung:**

1. Produkte markieren → Menü **Mehrfach-Operationen**
2. Modal öffnen → Shop, „Nur geänderte senden“, „Dry-Run“ wählen
3. Ergebnis-Toast zeigt Statusmeldungen

### CLI-Sync

```bash
php artisan app:woo-sync-product
php artisan app:woo-sync-variations
```

Optionen (falls implementiert):  
`--dry`, `--only-changed`, `--shop=ID`

### Logs

- Speicherort: `storage/logs/laravel.log`
- Prefixe:
  - `[SyncProductsBulkAction]`
  - `[SyncVariationsBulkAction]`
  - `[ProductExportOrchestrator]`
- `.env` prüfen bei API-Fehlern:
  - `WOO_API_BASE_URL`
  - `WOO_API_KEY`
  - `WOO_API_SECRET`

📘 **Detaillierte Anleitung:**  
siehe [`docs/Product-Sync-Guide.md`](docs/Product-Sync-Guide.md)

---

## 📡 Webhooks (Inbound)

WooCommerce sendet Produkt-Events an:

```txt
POST /api/webhooks/woo/{shopId}
```

Header:

```txt
X-WC-Webhook-Topic: product.updated
X-WC-Webhook-Signature: <HMAC>
```

Verarbeitung:
`App\Http\Controllers\WooWebhookController::handle()`  
→ ruft intern den `ProductExportOrchestrator` auf.

---

## 🧾 Logging & Fehleranalyse

- **Datei:** `storage/logs/laravel.log`
- **Log-Level:** `.env → LOG_LEVEL=debug`
- **Anzeige:** Filament zeigt Toasts nach jedem Sync
- **Fehlercodes:**
  - `400` → Ungültige Payload
  - `401` → Falsche Woo-Credentials
  - `404` → Parent-Produkt fehlt
  - `500` → Serverfehler (Woo oder App)

---

## 🧹 Wartung & Refactoring

### Aktuell erledigt

- Konsistente BulkActions (Products + Variations)
- Orchestrator als Single-Entry-Point
- Logging vereinheitlicht (`Log::info`, kein Backslash)
- Config-Docs für Woo-Settings

### Nächste Schritte

- `DispatchVariationsSyncJob` (Queue-Mode)
- Erweiterte XML-Importer-Basis
- Cleanup alter Migrations
- README-Erweiterung um API-Endpoints

---

## Eigene Artisan-Commands

| Kommando | Was es tut |
| ---------- |------------ |
| `php artisan import:smoke-aliens-sync` | einfacher empfohlener Smoke-Test für Dispatch (Queue)<br>nimmt automatisch die neueste CSV-Datei |
| `php artisan import:smoke-aliens-sync --manufacturer=1 --file=/usr/home/geoalp/wc-ga-api/storage/app/imports/DEINE.csv --author=1` | Smoke-Test: Sync (ohne Queue) ✅✅ (bestes Debugging)<br>Die Datei mit `ls -lt storage/app/imports \| head` suchen (oberste Datei ist es) |
| `php artisan products:purge --force` | Datenbank `products`- und `product_*`-Tabellen komplett leeren |
| `php artisan products:purge --manufacturer=1 --force` | Datenbank wie oben aber nur für einen bestimmten Hersteller leeren (hier Aliens mit der ID 1) |
| `php artisan aliens:images:download --manufacturerId=123 --limit=5`<br>`php artisan aliens:images:download --manufacturerId=123 --force` | Führt einen Bilder Download nach `storage/public` durch

---

## Checkliste: Petzl Import über UI – sauber & reproduzierbar

### 1) Aufräumen (vor dem UI-Import)

#### 1.1 Petzl-Produkte löschen (per Artisan)

→ Ziel: Alle bestehenden Petzl-Produkte + Variationen entfernen

```bash
php artisan products:purge --manufacturerId=3
```

#### 1.2 Queue & Batches aufräumen

→ Ziel: Alte Import-Jobs, fehlgeschlagene Jobs und Batches entfernen

```bash
php artisan queue:flush
```  

```bash
php artisan queue:prune-batches --hours=24
```  

```bash
php artisan queue:prune-failed --hours=24
```

### 2) Petzl Import über das UI starten

1. Filament öffnen
2. Import-/Herstellerbereich aufrufen
3. Hersteller **Petzl** auswählen
4. Import starten
5. Warten, bis der Import abgeschlossen ist (Status/Log beachten)

### 3) Ergebnis prüfen (Datenbank)

#### 3.1 Pflichtprüfung: original_product_name

→ Ziel: original_product_name darf NICHT NULL sein

```bash
SELECT id, product_name, original_product_name, product_type
FROM products
WHERE manufacturer_id = 3
ORDER BY id DESC
LIMIT 20;
```

#### 3.2 Varianten-Zählung prüfen

→ Ziel:

- echte variable Produkte: mehrere Variationen
- Einzelprodukte (1 Variation): später als simple behandelt

```bash
SELECT p.id, p.product_type, COUNT(v.id) AS variations
FROM products p
LEFT JOIN product_variations v ON v.product_id = p.id
WHERE p.manufacturer_id = 3
GROUP BY p.id, p.product_type
ORDER BY variations DESC
LIMIT 20;
```

### 4) UI-Stichprobe (ohne CLI)

1. Filament → Produktliste
2. Ein Petzl-Produkt öffnen
3. Prüfen:
   - original_product_name ist gefüllt
   - product_name beginnt **nur einmal** mit `PETZL -`
   - keine mehrfachen Eigenschaften (z. B. Farbe nicht 6×)
   - Anzahl der Variationen plausibel

### 5) Fehlerfall – Sofortdiagnose

#### 5.1 Prüfen, ob original_product_name leer ist

→ Wenn JA: Import schreibt das Feld nicht korrekt

```bash
SELECT id, product_name, original_product_name, slug
FROM products
WHERE manufacturer_id = 3
  AND (original_product_name IS NULL OR original_product_name = '')
LIMIT 50;
```

#### 5.2 Prüfen, ob die Spalte beschreibbar ist

→ Wichtig bei schema-robustem Import

```bash
SHOW COLUMNS FROM products LIKE 'original_product_name';
```

## 🧰 Entwickler-Tools

| Zweck | Pfad |
| ------- | ------ |
| Artisan Commands | `app/Console/Commands/` |
| Testskripte | `tests/bin/` |
| Woo-Services | `app/Services/Woo/` |
| Filament-Actions | `app/Filament/Resources/ProductResource/Actions/` |
| Importer-Basis | `app/Importers/` |
| Mapping-Dateien | `config/import_mappings/*aliens*.php` |

---

## 👨‍💻 Autor

**Jörg Aderhold**  
JAderBass web’n’more – Erfurt  
[www.jaderbass.de](https://www.jaderbass.de)

---

© 2025 JAderBass web’n’more · Stand 2025-10-17
