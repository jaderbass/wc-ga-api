# GeoAlpin WooCommerce Sync

Dieses Projekt erweitert die GeoAlpin-Plattform um eine stabile **Import- und Export-Pipeline für WooCommerce-Produkte**.  
Es ermöglicht, Produktdaten aus Herstellerquellen (CSV/XML) einzulesen, in der Datenbank zu speichern und anschließend  
in ein WooCommerce-kompatibles Format zu exportieren.

---

## 1. Installation

### 1.1 Voraussetzungen

- PHP 8.2+
- Composer
- MySQL 8.x
- Node.js + npm
- Laragon (oder gleichwertige lokale Umgebung)

### 1.2 Projekt einrichten

```bash
git clone https://github.com/jaderbass/wc-ga-api.git
cd wc-ga-api
composer install
npm install && npm run build
```

### 1.3 .env konfigurieren

- Datenbank-Zugangsdaten (`DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- Optional: zusätzliche Verbindungen (z. B. `mysql_dump` für Schema-Dump)

---

## 2. Datenbank

### 2.1 Migrationen & Seed

```bash
php artisan migrate --seed
```

### 2.2 Schema-Dump (für Neuinstallationen)

```bash
php artisan schema:dump --prune
```

→ erstellt `database/schema/mysql-schema.sql` als Baseline.

### 2.3 Neue Installation (komplett von Null)

```bash
git clone https://github.com/jaderbass/wc-ga-api.git
cd wc-ga-api
composer install
npm install && npm run build
cp .env.example .env   # Zugangsdaten anpassen
php artisan migrate --seed
```

---

## 3. Artisan Commands – WooCommerce Sync

### 3.1 Backfill Commands

- **`php artisan products:backfill`**  
  Füllt fehlende Felder in der `products`-Tabelle auf (z. B. Maße, Gewicht).  
  Optionen:  
  - `--chunk=1000` → Anzahl Produkte pro Durchlauf.  
  - `--dry-run` → Nur Anzeige, keine DB-Änderung.

- **`php artisan variations:backfill`**  
  Füllt fehlende Felder in der `product_variations`-Tabelle auf.  
  Optionen:  
  - `--chunk=1000` → Anzahl Variationen pro Durchlauf.  
  - `--dry-run` → Nur Anzeige, keine DB-Änderung.

### 3.2 Export Commands

- **`php artisan woo:export:sample`**  
  Erstellt eine kleine CSV-Stichprobe für den WooCommerce-Import.  
  Optionen:  
  - `--manufacturer=Edelrid` → Herstellername für Mapping.  
  - `--limit=5` → Anzahl Produkte im Export.  
  - `--out=storage/app/woo-export-sample.csv` → Zielpfad für CSV-Datei.

---

## 4. Hinweise

- Exportierte CSVs sind kompatibel mit dem WooCommerce-Importer.  
- Preisspalten (`regular_price`, `sale_price`, etc.) werden **nie** exportiert (Policy).  
- Attribute ohne Präfix `pa_` werden **nicht** exportiert.  
- Logs prüfen (`storage/logs/laravel.log`), falls Export/Backfill fehlschlägt.  

---

## 5. Tags & Versionierung

- Stabile Snapshots werden als Tags veröffentlicht (z. B. `v0.3.1-import-stable`).  
- Feature-Branches: `feature/...` (z. B. `feature/woo-export`).  
- PRs laufen über GitHub und werden via **Squash & Merge** integriert.

---

## 6. Nächste Schritte

- Hersteller-spezifische Maps (`app/Support/Woo/ManufacturerMaps/...`) erweitern.  
- Exporter für vollständigen WooCommerce-Import anpassen.  
- Weitere Hersteller (Kask, Singing Rock XML) einbinden.  

## 7. 🔌 WooCommerce API-Verbindung testen

Bevor Produkt- oder Varianten-Syncs ausgeführt werden, sollte geprüft
werden, ob die Laravel-App erfolgreich eine Verbindung zur WooCommerce
REST API mit den in der `.env` hinterlegten Zugangsdaten herstellen
kann.

### 1) Environment Setup

In der `.env` folgende Einträge hinzufügen (Domain und API-Keys
anpassen):

``` env
WOO_SYNC_ENABLED=true
WOO_API_BASE_URL=https://testshop.geoalpin.com
WOO_API_VERSION=wc/v3
WOO_API_KEY=ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
WOO_API_SECRET=cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Danach den Config-Cache leeren:

``` bash
php artisan config:clear
php artisan cache:clear
```

### 2) Sanity-Check Command ausführen

Mit dem eingebauten Artisan-Command prüfen:

``` bash
php artisan woo:ping
```

### 3) Erwartete Ausgabe

- **Erfolgsfall:**
  - Konsole zeigt `HTTP Status: 200`
  - Anzahl der Produkte (kann 0 sein, wenn der Shop leer ist)
  - JSON-Snippet des ersten Produkts (in der Konsole evtl.
        abgeschnitten)
- **Fehlerfall:**
  - Konsole zeigt Fehlermeldung (401 Unauthorized, 403 Forbidden,
        404 Not Found, etc.)
  - Fehler wird zusätzlich in `storage/logs/laravel.log`
        protokolliert

### 4) Troubleshooting

- **401 / 403 Unauthorized** → API-Key/Secret prüfen, Berechtigung
    *Lesen/Schreiben* setzen.
- **404 Not Found** → Prüfen, ob `WOO_API_BASE_URL` exakt der Shop-URL
    entspricht.
- **Timeout / Verbindung abgelehnt** → Internet/SSL-Setup prüfen.
- **Leeres Ergebnis** → Shop ist erreichbar, enthält aber keine
    Produkte.

### 5) Nächste Schritte

Sobald der `woo:ping` Command funktioniert, können gefahrlos Syncs
gestartet werden:

``` bash
php artisan woo:sync:variations --id=123
```

oder in Filament die Bulk Action **„Sync Variations to Woo"** ausführen.
