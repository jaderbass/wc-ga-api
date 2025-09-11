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
