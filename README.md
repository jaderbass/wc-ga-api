# 🌄 GeoAlpin – Produkt- & Varianten-Sync zwischen Laravel/Filament und WooCommerce

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20)
![Filament](https://img.shields.io/badge/Filament-3.2-F59E0B)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1)
![WooCommerce](https://img.shields.io/badge/WooCommerce-REST%20API%20v3-96588A)
![Status](https://img.shields.io/badge/Status-Active-success)

## 📚 Inhaltsverzeichnis

- [🧭 Projektüberblick](#-projektüberblick)
- [⚙️ Systemvoraussetzungen](#️-systemvoraussetzungen)
- [🚀 Installation & Setup](#-installation--setup)
- [🧩 Import-Module](#-import-module)
- [🔄 Produkt- & Varianten-Synchronisation](#-produkt---varianten-synchronisation)
- [🗂️ Kategorien & Regelbasierte Zuordnung](#️-kategorien--regelbasierte-zuordnung)
- [📡 Webhooks (Inbound)](#-webhooks-inbound)
- [🧾 Logging & Fehleranalyse](#-logging--fehleranalyse)
- [🧹 Wartung & Refactoring](#-wartung--refactoring)
- [🛠️ Eigene Artisan-Commands](#️-eigene-artisan-commands)
- [✅ Checkliste: Petzl Import über UI – sauber & reproduzierbar](#-checkliste-petzl-import-über-ui--sauber--reproduzierbar)
- [🧰 Entwickler-Tools](#-entwickler-tools)
- [👨‍💻 Autor](#-autor)

Dieses Projekt automatisiert den Import, die Verwaltung und den Export von Produktdaten
zwischen dem Laravel-/Filament-Backend und WooCommerce-Shops.

Entwickelt von **JAderBass web’n’more** (Jörg Aderhold)  
Stand: 2026-04-01

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

Für automatisierte Abläufe oder Tests können Produkte, Varianten und Kategorien
auch über Artisan synchronisiert werden:

[CODEBLOCK_SYNC_COMMANDS]

Optionale Parameter für Woo-Sync-Kommandos (wenn implementiert):

| Option | Beschreibung |
| ------ | ------------ |
| `--dry` | Nur Simulation, keine Schreiboperationen |
| `--only-changed` | Nur geänderte Datensätze synchronisieren |
| `--shop=ID` | Zielshop wählen |

#### Kategorien-Resync

[CODEBLOCK_CATEGORIES_RESYNC]

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

---

## 🗂️ Kategorien & Regelbasierte Zuordnung

Produkte können mehreren Kategorien gleichzeitig zugeordnet werden.  
Die Kategorisierung erfolgt auf zwei Wegen:

- **Automatisch** über regelbasierte Keywords je Kategorie
- **Manuell** über die Produkt-Detailansicht im Filament-Backend

### Verhalten

- Ein Produkt kann mehreren Kategorien gleichzeitig zugeordnet sein.
- Automatische und manuelle Zuordnungen werden getrennt behandelt.
- **Manuell gesetzte Kategorien bleiben immer erhalten** und werden durch die Automatik nicht überschrieben oder gelöscht.
- Die automatische Kategorisierung kann mehrere Treffer gleichzeitig liefern.

### Pflege im Backend

- Neuer Navigationspunkt **Kategorien**
- CRUD für Kategorien
- Regeln pro Kategorie direkt im UI pflegbar
- Regeln werden in der Datenbank gespeichert, nicht mehr im PHP-Code

### Resync bestehender Produkte

Wenn Regeln geändert wurden, können bestehende Produkte per Artisan neu kategorisiert werden:

```bash
php artisan categories:resync
php artisan categories:resync --manufacturer-id=6
php artisan categories:resync --product-id=123
php artisan categories:resync --manufacturer-id=6 --dry-run
php artisan categories:resync --manufacturer-id=6 --queue
```

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

## 🛠️ Eigene Artisan-Commands

| Kommando | Was es tut |
| ---------- | ------------ |
| `php artisan import:smoke-aliens-sync` | Einfacher empfohlener Smoke-Test für Dispatch (Queue). Nimmt automatisch die neueste CSV-Datei. |
| `php artisan import:smoke-aliens-sync --manufacturer=1 --file=/usr/home/geoalp/wc-ga-api/storage/app/imports/DEINE.csv --author=1` | Smoke-Test: Sync ohne Queue. Praktisch für direktes Debugging. Die Datei mit `ls -lt storage/app/imports \| head` suchen. |
| `php artisan products:purge --force` | Datenbank `products`- und `product_*`-Tabellen komplett leeren. |
| `php artisan products:purge --manufacturer=1 --force` | Datenbank wie oben, aber nur für einen bestimmten Hersteller leeren. |
| `php artisan aliens:images:download --manufacturerId=123 --limit=5`<br>`php artisan aliens:images:download --manufacturerId=123 --force` | Führt einen Bilder-Download nach `storage/public` durch. |
| `php artisan categories:resync` | Synchronisiert die Kategorien aller Produkte anhand der aktuellen Kategorie-Regeln neu. |
| `php artisan categories:resync --product-id=123` | Synchronisiert nur ein bestimmtes Produkt neu. |
| `php artisan categories:resync --manufacturer-id=6` | Synchronisiert nur Produkte eines bestimmten Herstellers neu. |
| `php artisan categories:resync --manufacturer-id=6 --dry-run` | Testlauf ohne Änderungen. Zeigt nur, welche Produkte verarbeitet würden. |
| `php artisan categories:resync --manufacturer-id=6 --queue` | Startet den Kategorien-Resync als Queue-Job. |

---

## ✅ Checkliste: Petzl Import über UI – sauber & reproduzierbar

### 1) Aufräumen (vor dem UI-Import)

#### 1.1 Produkte löschen (per Artisan)

→ Ziel: Alle bestehenden Produkte + Variationen entfernen

```bash
php artisan products:purge --force
```

→ Ziel: Alle bestehenden Produkte + Variationen eines bestimmten Herstellers entfernen (ID mit der Hersteller-ID ersetzen) z.B. für Petzl: 3

```bash
php artisan products:purge --manufacturer=<ID>
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

© 2025 JAderBass web’n’more · Stand 2026-03-12
