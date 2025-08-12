# Projektkontext – WooCommerce GeoAlpin API

## 1. Projektbeschreibung

Laravel 10 + Filament 3.2 basiertes Admin-Dashboard zur Synchronisation und Verwaltung von Produktdaten zwischen Lieferanten (CSV, XML, API) und WooCommerce.
Ziel:  

- Produkte von verschiedenen Herstellern importieren (CSV/XML/API)
- Varianten (Farbe, Größe etc.) korrekt zuordnen
- Bestehende Produkte bei Import aktualisieren, nicht duplizieren

---

## 2. Modelle & Tabellen

**products**  

- Enthält Hauptprodukt-Daten (simple oder variable)
- `slug` ist eindeutig und wird beim Import deterministisch aus dem Namen erzeugt
- Felder wie `productnumber`, `mpn`, `price`, Maße etc.
- `manufacturer_id` → relation zu manufacturers

**product_variations**  

- Enthält Varianten zu `products` (Farbe, Größe, Preise, Lagerbestand)
- Verbindung über `product_id`

**manufacturers**  

- Herstellername, Importtyp (csv/xml/api), Mapping-Datei

---

## 3. Import-Logik

Verantwortlich: `GenericCsvProductImporter` (und später `BaseXmlImporter`)  
Ablauf:

1. CSV einlesen (League\Csv\Reader)
2. Gruppieren nach Hauptprodukt-Feld (`group_by` aus Mapping)
3. Für jede Gruppe:  
   - Prüfen ob Produkt existiert (Match: slug + optional manufacturer_id)  
   - Falls ja: **Update**  
   - Falls nein: **Anlegen**  
4. Varianten iterieren und anlegen/updaten (matching über `sku`)

### Besonderheiten

- Slug wird deterministisch erzeugt (`Str::slug($name)`), um Dubletten zu vermeiden
- Name wird robust bestimmt (nimmt erste nicht-leere Zeile aus Gruppe)
- Fehlende Referenz → Variante wird übersprungen
- Mapping-Dateien liegen unter `config/import_mappings/<hersteller>.php`

---

## 4. Offene Punkte

- [ ] Option zum manuellen Löschen aller Produkte + Varianten (Bulk-Action existiert bereits)
- [ ] FileUpload-Validierung verbessert (aktuell nur Notification bei fehlender Datei)
- [ ] API-Import-Modul fertigstellen
- [ ] XML-Importer umsetzen
- [ ] Bessere UX: Import-Popup bei Fehlermeldung offen halten

---

## 5. Entwicklungsumgebung

- **Lokale Repos:** Desktop + Laptop synchron über Git
- `.env` ist auf beiden Geräten korrekt konfiguriert
- Datenbankstände sind synchron
- Genie AI im VSCode aktiv, Kontext kommt über diese Datei
- Kein Autostart von Vite mehr aktiv

---

## 6. Letzte Änderungen

- Update statt Neuanlage bei Import (`importProductGroup` angepasst)
- Name-Ermittlung verbessert (nicht mehr "Unnamed Product" wenn CSV-Feld leer ist)
- Varianten-Import repariert (jetzt werden Varianten korrekt zugeordnet)
- Bulk-Delete in ProductResource funktionsfähig

---

## 7. Wichtig für Genie

- **Ziel:** Saubere, erweiterbare Import-Architektur, die CSV, XML und API-Quellen unterstützt
- **Sprache:** Deutsch bevorzugt, Code-Kommentare zweisprachig (de/en)
- **Stil:** Klare, kommentierte Laravel-/Filament-Beispiele
