# Regeln für Produktnamen (PS Alpin)

Diese Regeln gelten für alle Produkte, die in der PS-Alpin-Produktdatenbank aus Herstellerlisten eingelesen, vereinheitlicht und später in den Shop übertragen werden. Sie wurden von Maik Reinke und Jörg Aderhold festgelegt.

Stand: 2026-10-09. Abschnitt 6 beschreibt, wo der Code heute noch von den Regeln abweicht.

## 1. Format

```
HERSTELLER - Kategorie - Produktname - Eigenschaft - Eigenschaft …
```

| Baustein | Regel | Beispiel |
|---|---|---|
| Hersteller | immer in Großbuchstaben | `PETZL` |
| Kategorie | Shop-Kategorie des Produkts (siehe Abschnitt 5) | `Seile` |
| Produktname | Bezeichnung des Herstellers, ohne wiederholten Herstellernamen, Wörter beginnen groß | `Volta` |
| Eigenschaft | Merkmal wie Durchmesser, Größe, Farbe, Länge, mit großem Anfangsbuchstaben | `Orange`, `60 m` |
| Trenner | Leerzeichen, Bindestrich, Leerzeichen: ` - ` | |

## 2. Regeln je Produktart

**Einzelprodukt:** Hersteller - Kategorie - Produktname - bis zu 3 Eigenschaften

```
PETZL - Seile - Volta - Orange - 60 m
EDELRID - Karabiner - HMS Strike Screw
```

**Stammprodukt (variables Produkt):** Hersteller - Kategorie - Produktname - höchstens 1 Leit-Eigenschaft.
Die Leit-Eigenschaft ist die Eigenschaft, die für alle Varianten gleich ist. Welche das ist, hängt von der Kategorie ab:

| Kategorie | Leit-Eigenschaft (Reihenfolge der Prüfung) |
|---|---|
| Seile | Durchmesser aus der Bezeichnung (`10,5 mm`), sonst Durchmesser, Größe, Länge, Farbe, Version |
| Handschuhe | Größe, Farbe, Version, Länge, Durchmesser |
| Karabiner | Version, Größe, Farbe, Länge, Durchmesser |
| alle anderen | Größe, Version, Durchmesser, Länge, Farbe |

Gibt es über alle Varianten nur einen Eigenschaftstyp, steht beim Stammprodukt keine Eigenschaft.

```
COURANT - Seile - Ultima - 10,5 mm
```

**Variante:** Name des Stammprodukts, dahinter nur die Eigenschaften, in denen sich die Varianten unterscheiden, in dieser Reihenfolge: Durchmesser, Größe, Typ, Version, Farbe, Länge. Die Leit-Eigenschaft des Stammprodukts wird nicht wiederholt.

```
COURANT - Seile - Ultima - 10,5 mm - Gelb - 50 m
```

**Set:** wie Einzelprodukt, bis zu 3 Eigenschaften.

## 3. Schreibweise

- **Großschreibung:** Jedes Wort des Produktnamens beginnt groß, der Rest klein (`AXIS 11 MM` wird zu `Axis 11 mm`).
- **Abkürzungen** bleiben groß, wenn sie in `config/product_name.php` unter `abbreviations` stehen (heute: UIAA, CE, ANSI, EN, ISO).
- **Einheiten** stehen mit Leerzeichen hinter der Zahl und in fester Schreibweise: `10 mm`, `60 m`, `22 kN`, `10 x 120 cm`.
- **Markenzeichen** wie ® werden entfernt.
- **Herstellername am Anfang der Bezeichnung** wird entfernt, damit er nicht doppelt erscheint (`Petzl Vertex` wird zu `Vertex`).
- **Größencodes** bleiben groß: `S-M`, `L-XL`, `XXL`.

## 4. Farben

Farben im Namen kommen aus dem Menü **„Farben“** (Farb-Übersetzungen):

- Grundfarben werden übersetzt: `Yellow` → `Gelb`.
- Kombinationen mit Bindestrich: `White/Red` → `Weiß-Rot`.
- Hell, Dunkel, Leucht…: `Dark gray` → `Dunkelgrau`, `Orange Fluo` / `HiVis yellow` → `Leuchtorange` / `Leuchtgelb`.
- Sonderfarben des Herstellers bleiben im Original, mit großem Anfangsbuchstaben: `night` → `Night`, `oasis-grey` → `Oasis-Grey`.
- Manuelle Einträge im Menü „Farben“ haben immer Vorrang.

## 5. Kategorie im Namen

Im Namen steht die **Shop-Kategorie**, also die einfache Verkaufsbezeichnung (zum Beispiel `Karabiner`, nicht `Verbindungselemente`).

**Ziel-Regel** (vereinbart, noch nicht vollständig umgesetzt), Reihenfolge:

1. Kategorie, die von Hand am Produkt gesetzt wurde
2. Kategorie aus der Herstellerliste, über die Zuordnung „Herstellerkategorie → Shop-Kategorie“
3. Stichwort-Regeln aus dem Menü „Kategorien“ (Begriffe im Produktnamen)
4. `Allgemein`, als Kennzeichen für „noch nicht zugeordnet, bitte prüfen“

## 6. Abweichungen im heutigen Code (offen)

| Thema | Heute | Soll |
|---|---|---|
| Kategorie | nur Stichwort-Regeln (Schritt 3), sonst `Allgemein`. Die Herstellerkategorie wird nur bei Petzl gespeichert (`petzl_source_category`), aber nicht für den Namen genutzt. | Reihenfolge aus Abschnitt 5 |
| Trenner | Code: ` - `; im Live-Shop oft ` – ` (langer Strich) | einheitlich ` - ` |
| Abkürzungen | `HMS` wird zu `Hms` (fehlt in der Liste); die Listen `manufacturer_overrides.preserve` (z. B. `GRIGRI`, `MEGA JUL`) sind angelegt, werden aber nirgends verwendet | Abkürzungen und Herstellernamen bleiben in Originalschreibweise |
| Bestehende Namen | wurden vor den Farb-Übersetzungen erzeugt (`… - night`) | nach Änderungen neu erzeugen (Abschnitt 7) |

## 7. Wo die Regeln im Code stehen

| Datei | Inhalt |
|---|---|
| `config/product_naming.php` | Reihenfolge der Bausteine je Produktart, Trenner, Platz für Abweichungen je Hersteller |
| `config/product_name.php` | Abkürzungen, Einheiten |
| `app/Services/ProductNaming/DefaultProductNameBuilder.php` | setzt den Namen zusammen |
| `app/Services/ProductNaming/ParentLeadPropertyResolver.php` | Leit-Eigenschaft des Stammprodukts |
| `app/Services/ProductNaming/VariationDisplayNameResolver.php` | Variantennamen |
| `app/Services/ProductNaming/ProductNameContext.php` | sammelt Hersteller, Kategorie, Bezeichnung, Eigenschaften |
| `app/Services/Categories/CategoryResolver.php` | Kategorie über Stichwort-Regeln |
| `app/Support/ProductNameNormalizer.php` | Groß-/Kleinschreibung, Einheiten |
| `tests/Datasets/ProductNamingCases.php` | Beispiele als Tests |

Befehle:

- `php artisan product:name-preview` zeigt eine Vorschau des Namens
- `php artisan products:rebuild-names` erzeugt die Namen in der Datenbank neu
- `php artisan products:names:backfill --dry-run` füllt fehlende Namen (mit `--dry-run` nur zur Ansicht)

## 8. Verbindliche Regel für die Übertragung in den Shop

Nur Produkte **mit Titelbild** werden in den Shop übertragen. Das Stammprodukt bzw. Einzelprodukt braucht immer ein Titelbild, sonst wird es nicht übertragen. Varianten brauchen kein eigenes Bild (zum Beispiel ein Seil in zehn Farben, mit oder ohne Bild je Farbe).
