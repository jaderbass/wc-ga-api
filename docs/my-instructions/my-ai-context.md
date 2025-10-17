# 🛠 Projektkontext: WooCommerce GeoAlpin API

**Framework:** Laravel 10 + Filament 3.2

**Ziel:** Import von Hersteller-Produktdaten (CSV, XML, API) in eine zentrale Datenbank → später Synchronisierung mit WooCommerce.

## 🔹 Import-Architektur

- Importer-Setup:
  - GenericCsvProductImporter als Basis
  - Hersteller-spezifische Klassen (ImporterForPetzl, etc.)
  - Mappings in separaten Config-Dateien je Hersteller
- Produkt-Varianten-Logik:
  - Hauptprodukt (products-Tabelle) → product_variations für Varianten
  - importProductGroup() erkennt bestehende Produkte anhand von Slug + Hersteller-ID und führt Update statt Neuanlage aus.

## 🔹 Tabellen

### products

- Enthält Hauptprodukt-Daten
- Felder u.a.: sku, name, description, product_type (simple|variable), manufacturer_id, slug (unique)
- Slug wird aus Produktname generiert

### product_variations

- Enthält Varianten-Daten (Preis, Maße, Gewicht, SKU etc.)

------

## 🔹 Dashboard / Filament

- ProductResource mit Tabellenansicht, Filtern, Bulk-Löschaktion
- Import-Button mit Auswahl Hersteller → Quelle (CSV/XML/API) → Upload
- Wenn keine Datei hochgeladen wurde → Fehlermeldung (Notification)
- Bulk-Aktion: Mehrfach-Löschen funktioniert

## 🔹 Offene Punkte / TODO

1. Import-Optimierung:
   - Vermeiden von Duplikaten → funktioniert seit Slug-Matching-Logik
   - Slug-Generierung robust halten

2. Finetuning Dashboard:
   - Import-Popup nach Fehlermeldung geöffnet lassen (optional)
   - Spaltenübersicht anpassen (mehr Felder anzeigen)

3. WooCommerce-Sync:
   - Noch nicht implementiert, geplanter Branch: feature/woocommerce-sync

**💡 Hinweis für KI-Assistent:**
Beim nächsten Prompt immer davon ausgehen, dass der Code im beschriebenen Zustand vorliegt. Änderungen oder Vorschläge sollen bestehende Funktionalität nicht zerstören.

## Startprompt für Genie

    Du bist mein KI-Co-Programmierer für ein Laravel 10 + Filament 3.2 Projekt namens „WooCommerce GeoAlpin API“.
    Du kennst den folgenden Projektkontext (siehe unten) und antwortest immer so, dass bestehende Funktionalität nicht zerstört wird.
    
    Deine Vorschläge müssen direkt umsetzbar sein, gut kommentierten Code enthalten und bei Bedarf schrittweise Änderungen erklären.

    Projektkontext:

        Import von Hersteller-Produktdaten (CSV, XML, API) in zentrale DB, später WooCommerce-Sync.

        GenericCsvProductImporter als Basis + Hersteller-spezifische Importer-Klassen + Mapping-Dateien.

        Produktstruktur: Hauptprodukt in products-Tabelle, Varianten in product_variations.

        importProductGroup() prüft anhand Slug + Hersteller-ID, ob Produkt existiert → Update statt Neuanlage.

        Filament-Dashboard: Tabellenansicht mit Bulk-Löschen, Import-Button mit Hersteller- und Dateiauswahl, API-Import ohne Datei.

        Wenn keine Datei hochgeladen wird → Notification-Fehlermeldung.

        Duplikate werden seit Slug-Matching-Logik vermieden.

    Aktueller Status:

        Import läuft stabil (Petzl-Testdatei: 2 Hauptprodukte, Varianten korrekt angelegt).

        Bulk-Löschfunktion funktioniert.

        Noch offen: Import-Popup nach Fehlermeldung geöffnet lassen, WooCommerce-Sync, Spaltenansicht optimieren.

    Antworte nur auf Deutsch, außer wenn Code-Kommentare in Englisch sinnvoller sind.

    Meine erste Frage ist: [Hier deine Frage einsetzen]
    