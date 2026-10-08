# Kategorie-Zuordnung: Herstellerlisten → Shop-Kategorien

Stand: 08.10.2026 · Entscheidungen: Maik (PS Alpin) · Ergänzt [Produktnamen-Regeln](Produktnamen-Regeln.md)

Dieses Dokument hält fest, **woher** die Kategorie eines Produkts in jeder Herstellerliste kommt und **in welche Shop-Kategorie** sie fällt. Die Tabellen sind die Vorlage für die Umsetzung in der Datenbank.

---

## 1. Grundprinzip

1. **Herstellerkategorie roh speichern** – bei jedem Produkt, für jeden Hersteller (heute nur Petzl, Tabelle `petzl_category_mappings`). Der Wert bleibt so, wie er in der Liste steht.
2. **Zuordnung** Herstellerkategorie → Shop-Kategorie (viele-zu-eins erlaubt), pflegbar im Backend für alle Hersteller. Die Petzl-Tabelle bleibt daneben bestehen (Übersetzung + Petzl-Produktseiten für den Beschreibungs-Sync).
3. **Stichwort-Ausnahmen** je Hersteller: Wenn eine Herstellerkategorie gemischt ist (z. B. Edelrid „VERBINDUNGSMITTEL“ enthält auch Schlingen), korrigiert ein Stichwort im Produktnamen die Kategorie für dieses eine Produkt.
4. Reihenfolge bei der Kategorie eines Produkts:
   **manuell gesetzt → Herstellerkategorie über Zuordnung (+ Stichwort-Ausnahme) → allgemeine Stichwort-Regeln (`CategoryResolver`) → „Allgemein“** (= muss geprüft werden)
5. Einsatzgebiete (Baumpflege, Feuerwehr, PSAgA …) werden zusätzlich vergeben – ein Produkt kann mehrere haben (siehe Abschnitt 5).

---

## 2. Neue Kategorien (noch anzulegen, in Datenbank und Shop)

| Neu | Ebene | Kommt von |
|---|---|---|
| Höhensicherungsgeräte | Produkte | Kratos, Aliens (KONG, Protekt, ISC) |
| Werkzeugsicherung | Produkte | Kratos, Aliens (Spiralkabel, Tool Frog, Tool Leash, Zubehör-/Materialkarabiner), Edelrid |
| Lastensicherung | Produkte | Kratos |
| Segel-Zubehör | Produkte | Aliens (Lanex) |
| Kletter-Zubehör | Produkte | Aliens (Sportkletterbedarf) |
| Baumpflege-Zubehör | Produkte | Aliens, Edelrid (Cambiumschoner, Wurfbeutel, Wurfleine, Steighilfe) |
| Rettung | Einsatzgebiet | Skylotec |
| Segeln | Einsatzgebiet | Aliens (Lanex) |

Kategorienamen sind in der Datenbank eindeutig – deshalb heißen die Produktkategorien „…-Zubehör“, die Einsatzgebiete „Baumpflege“, „Klettern“, „Segeln“.

Bestehende Shop-Kategorien, auf die zugeordnet wird (Auszug): Seile > Statisch / Dynamisch / Seilschutz, Reepschnur, Karabiner > Alukarabiner / Stahlkarabiner / Edelstahlkarabiner / Express-Set / Zubehör Karabiner, Gurte > Auffanggurt / Arbeitsgurt / Sitzgurt / Zubehör Gurte, Helme > Schutzhelme / Zubehör Schutzhelme, Verbindungsmittel (> mit Falldämpfer), Falldämpfer, Schlingen & Lanyard, Schraubglieder, Verbindungselemente, Seilrollen, Seilklemmen, Abseilgeräte, Sicherungsgeräte, Auffanggerät > Mitlaufende Auffanggeräte, Riggingplatten, Positionierung, Anschlageinrichtung, Dreibein (> Winde), Flaschenzüge, Rettung & Transport (> Tragen), Taschen & Rucksäcke, Handschuhe, Stirnlampe, Messer, Ersatzteil, Zubehör, Werbemittel, Sets > PSAgA-Sets, RFID Tec, SALE.

---

## 3. Umsetzung in der Datenbank

- **Kategorie-Struktur:** `php artisan db:seed --class=ShopCategoryTreeSeeder --force` legt die Shop-Struktur (Stand 08.10.2026) und die neuen Kategorien an. Mehrfach ausführbar; vorhandene Kategorien werden wiederverwendet, nichts gelöscht oder umbenannt.
- **Herstellerkategorie:** Spalte `products.source_category` (roh, wie in der Liste). Petzl: `Category > Subcategory` (beim Update einmalig aus den Petzl-Spalten befüllt, danach vom Petzl-Import gesetzt).
- **Backend „Hersteller-Zuordnung“** (`category_assignment_rules`):
  - Eintrag mit Herstellerkategorie = Zuordnung dieser Kategorie zu einer oder mehreren Shop-Kategorien
  - Eintrag mit Stichwort = Ausnahme; optional nur innerhalb einer Herstellerkategorie; ohne Hersteller = gilt für alle
  - „Ausschließen“ = keine automatische Kategorie; manuell gesetzte bleiben. **Noch offen:** Der spätere Shop-Import muss ausgeschlossene Produkte überspringen – heute gibt es dafür keinen Filter. Wo diese Doku „nicht importieren“ sagt, ist das das Ziel; technisch ist es vorerst „Ausschließen“.
  - Stichwörter mit `|` trennen; Treffer am Wortanfang (`ring` trifft „Ring“, nicht „Spring“); `*` davor = auch mitten im Wort (`*rolle` trifft „Umlenkrolle“)
  - unbekannte Herstellerkategorien erscheinen beim Import automatisch als „offen“
- **Neu zuordnen:** Button „Kategorien neu zuordnen“ auf der Seite oder `php artisan categories:resync`. Manuell gesetzte Kategorien bleiben immer erhalten. Es wird nichts in den Shop übertragen.

- **Preislisten einlesen** (Aliens, Edelrid, Petzl): ergänzt vorhandene Produkte, legt keine neuen an.
  ```
  php artisan pricelist:apply aliens storage/app/imports/<datei>.xlsx --dry-run   # Probelauf, nur Bericht
  php artisan pricelist:apply aliens storage/app/imports/<datei>.xlsx             # speichern
  php artisan pricelist:apply edelrid storage/app/imports/<datei>.xlsx
  php artisan pricelist:apply petzl storage/app/imports/<datei>.xlsx
  ```
  - Zuordnung je Zeile: Varianten-SKU = Artikelnr. → Produkt-SKU/Artikelnummer → Varianten-EAN → Produkt-EAN
  - setzt die Herstellerkategorie (Aliens: Produktart aus dem Kurztext, Edelrid: Zwischenüberschrift, Petzl: `Category > Subcategory`) und legt die Einträge in der Hersteller-Zuordnung an; leere, ungeprüfte Einträge bekommen den Vorschlag aus `config/price_lists.php` (geprüfte werden nie überschrieben)
  - `AUSVERKAUFT` (Aliens) bzw. `EOL` (Petzl), jeweils alle Zeilen eines Produkts → `online_sellable = false`; `ABVERKAUF` → Kategorie „SALE“ (Zuordnungsart `import`, verschwindet beim nächsten Einlesen ohne Markierung)
  - Petzl `NEW` → normal, plus Hinweis „Neu – lieferbar ab TT.MM.JJJJ“ (Meta `pricelist_petzl_availability`); **offen:** beim späteren Shop-Import als Lieferzeit „ab …“ in WooCommerce setzen
  - Listendaten (Artikelnr., Kurztext, HEK/UVP bzw. Listenpreis, Gewicht, Einheit, Zolltarif, Ursprungsland, Markierung, „verfügbar ab“) als Produkt-Meta `pricelist_<liste>`; leere EAN und leeres Variantengewicht werden ergänzt
  - Namen, Beschreibungen und Preise werden **nicht** überschrieben; kein Shop-Sync
  - nicht gefundene Zeilen: CSV unter `storage/app/exports/`

---

## 4. Je Hersteller

### 4.1 Petzl
- **Quelle:** Spalten `Category` / `Subcategory` (Import-CSV und Preisliste „PRO BASIC Price List 2027“, Blatt „Logistic Info“; Preise aus Blatt „Price List“) → Herstellerkategorie `Category > Subcategory`
- Die Übersetzungstabelle `petzl_category_mappings` bleibt für die Petzl-Produktseiten bestehen.
- **Zuordnung** (Maik, 08.10.2026; vollständig für alle 85 Unterkategorien in `config/price_lists.php` → `petzl.sources`), u. a.:
  - Gurte: **nur NEWTON → Auffanggurt** (Petzl-Seite „Auffanggurte“); alle anderen Gurte → **Arbeitsgurt** + Einsatzgebiet (Fall-Arrest/Energy & Networks/Rope Access → PSAgA, Rescue → Rettung, Tree Care → Baumpflege)
  - Positioning Lanyards → Positionierung; Hats & Balaclavas → Zubehör Schutzhelme
  - alle „Spare Parts for …“ → Ersatzteil **und** Zubehör der Gruppe, sofern vorhanden (Helme, Gurte, Abseilgeräte, Stirnlampen, Karabiner)
  - Tree-Care- und Rescue-Unterkategorien bekommen zusätzlich das Einsatzgebiet Baumpflege bzw. Rettung
- **Status:** `EOL` → nicht in den Shop (wie AUSVERKAUFT); `NEW` → normal + Hinweis „lieferbar ab …“

### 4.2 Skylotec
- **Quelle:** keine Kategoriespalte. eCl@ss (Spalte 15) nur teilweise brauchbar (~1.240 Zeilen in Sammelklassen „-90“, 295 leer).
- **Vorgehen:** konkrete eCl@ss-Klasse → Typ-Spalten (Karabinertyp, Seilart, Verbindungsmittel Typ, Seilklemmentyp, Rollentyp, Anschlagpunkt Typ) → Stichwort-Regeln.
- **Einsatzgebiete** aus Spalte 42 „Anwendungsgebiete“: siehe Abschnitt 5.
- Titelbild: Spalte 5 „Link Standardbild“.

### 4.3 Kratos (`export_christopher.xlsx`)
- **Quelle:** erster Pfadteil der Spalte „URL du produit“ (Groß-/Kleinschreibung, Akzente und Bindestrich am Ende vereinheitlichen, z. B. `acces-sur-cordes` = `Accès-sur-cordes`).

| Kratos | → Shop-Kategorie |
|---|---|
| Ancrages | Anschlageinrichtung |
| Lignes-de-vie-permanentes-et-supports-d-assurage-verticaux | Anschlageinrichtung |
| Harnais-et-Ceintures | Gurte (Unterkategorie je Produkt) |
| Antichutes-coulissants | Auffanggerät > Mitlaufende Auffanggeräte |
| Longes-avec-absorbeur-d-énergie | Verbindungsmittel > Verbindungsmittel mit Falldämpfer |
| Longes-et-longes-de-maintien | Verbindungsmittel |
| Connecteurs | Karabiner |
| Antichute-rappel-auto-câble | **Höhensicherungsgeräte (neu)** |
| Antichute-rappel-auto-sangle | **Höhensicherungsgeräte (neu)** |
| Longes-porte-outils | **Werkzeugsicherung (neu)** |
| Antichute-de-charges | **Lastensicherung (neu)** |
| Sauvetage-et-évacuation | Rettung & Transport |
| Sacs | Taschen & Rucksäcke |
| Kit-antichute | Sets > PSAgA-Sets |
| Lampes-frontales, lampes-frontales-kslight | Stirnlampe |
| Protection-de-la-tête | Helme > Schutzhelme |
| Accès-sur-cordes, Gamme-FREE-BLAST | keine feste Zuordnung – je Produkt über Stichwort-Regeln |
| Formations-…, Inspection/Installation | **nicht importieren** (Schulungen bieten wir nicht an) |

### 4.4 Aliens (`2025-Aliens-Preisliste-Artikelstammdaten-Hj-2.xlsx`)

Aliens ist **Großhändler** – die Liste enthält viele Marken: Tendon, Singing Rock, KONG, Teufelberger, Aliens (Eigenmarke), Alp Design, Peguet, Lanex, Rockhelmets, Edelweiss, Skedco, ISC, Hellberg, Protekt u. a. **Alle Marken werden importiert.**

> Hinweis: Der vorhandene `ImporterForAliens` / `resources/import_mappings/aliens.php` erwartet einen PrestaShop-Export (`Produkt-ID`, `Kombination-ID`) bzw. eine CSV mit `Artikelbezeichnung`. Die Preisliste hat ein anderes Format (Excel, Spalten unten) → eigenes Mapping oder eigener Importer nötig.

- **Spalten:** Artikelnr., Kurztext, HEK netto, UVP netto, Gewicht, Einheit, Warentarifnr., Ursprungsland, EAN Barcode – **keine Kategorie, keine Bilder**
- **Aufbau Kurztext:** `[Markierung] MARKE - Produktart MODELL - Eigenschaft`
  Beispiel: `-- ABVERKAUF -- KONG - Alukarabiner PADDLE - BENT GATE - schwarz`
  - Marke = Teil vor dem ersten ` - `
  - Produktart = Wörter danach bis zum ersten Wort in GROSSBUCHSTABEN (= Modellname)
  - 13 von 4.539 Zeilen haben kein ` - ` → „Allgemein“

**Markierungen am Anfang des Kurztexts** (immer aus dem Namen entfernen):

| Markierung | Anzahl | Behandlung |
|---|---|---|
| `--AUSVERKAUFT-- MM/JJ` | 170 | in die Datenbank, **nicht in den Shop** |
| `-- ABVERKAUF --` | 153 | normal importieren, **zusätzlich Kategorie „SALE“** |
| `** NEU MM/JJ **` | 141 | normal (offen: Kennzeichnung „Neu“?) |
| `## DNV ##` | 8 | offen |
| `-- SONDERANFERTIGUNG --` | 3 | offen |

**Zuordnung über Stichwörter im Kurztext** (Reihenfolge = Priorität, erste Regel gewinnt; Groß-/Kleinschreibung egal). Damit landen 4.398 von 4.539 Zeilen (97 %) automatisch in einer Kategorie; die restlichen 141 sind Einzelstücke → „Allgemein“.

| # | Stichwörter (Auszug) | → Shop-Kategorie | Zeilen |
|---|---|---|---|
| 1 | Overall, Unterziehanzug, Sweatshirt, Hoody, Mütze, Beanie, Warnweste, Schal, Shirt | **nicht importieren** (Bekleidung) | 74 |
| 2 | Hundegurt, Hundegeschirr | Rettung & Transport | 20 |
| 3 | Visier, Gehörschutz, Helmhalter, Mikrofon, Hängesystem | Helme > Zubehör Schutzhelme | 50 |
| 4 | Höhensicherungsgerät, Retractable, „für SRL“ | **Höhensicherungsgeräte (neu)** | 12 |
| 5 | Höhensicherungsset, Fall Arrester Set, Rescue Set, Roofer Set, Scaffolder Set, Gerätesatz | Sets > PSAgA-Sets | 18 |
| 6 | Spiralkabel, Tool Frog, Tool Leash, Werkzeug…, **Zubehörkarabiner, Materialkarabiner** | **Werkzeugsicherung (neu)** | 195 |
| 7 | Cambium-/Kambiumschoner, Wurfbeutel, Wurfleine, Steighilfe, Throwing Bag | **Baumpflege-Zubehör (neu)** | 27 |
| 8 | Statikseil, Arbeitsseil, Klemmknotseil, Prusikseil, Lowering Rope, Aramidseil, Kernmantel, eSTATIC | Seile > Statisch | 1.088 |
| 9 | Kletterseil, Einfachseil, Halb-/Zwillingsseil, dynamische Seil…, „Seil …“ am Anfang | Seile > Dynamisch | 731 |
| 10 | Reepschnur | Reepschnur | 135 |
| 11 | Gummileine, GumFix, Hobbyschnur, Yachting rope, Outdoorseil, Segelleine, Polyester-/Vectran-Seil, Lanyard elastisch | **Segel-Zubehör (neu)** + Einsatzgebiet **Segeln (neu)** | 66 |
| 12 | bronze, marinebronze, Swivel, Snap, Buckle, D Shape (KONG-Beschläge, VE 50–500) | Zubehör | 175 |
| 13 | Felshaken, Klemmgerät, Klemmkeil, Chalk, Magnesia, Steigeisen, Armtrainer, Beta Stick, Seilbürste, Klettergriff, Eispickel, Finger Tape, Sicherungsbrille, Klettersteig…, Bohrhakenlasche | **Kletter-Zubehör (neu)** | 103 |
| 14 | Express-Set, Expressset, Quick Draw | Karabiner > Express-Set | 51 |
| 15 | Expressschlinge | Schlingen & Lanyard | 13 |
| 16 | Edelstahlkarabiner | Karabiner > Edelstahlkarabiner | 28 |
| 17 | Stahlkarabiner, Einhandkarabiner Stahl | Karabiner > Stahlkarabiner | 51 |
| 18 | Alu…karabiner, Einhandkarabiner, „Karabiner“ am Anfang | Karabiner > Alukarabiner | 295 |
| 19 | Karabinerfixierung | Karabiner > Zubehör Karabiner | 4 |
| 20 | Schraubglied, Schraubkettenglied | Schraubglieder | 79 |
| 21 | Ring, Aluring, Schnelltrennung, Quick Release | Verbindungselemente | 24 |
| 22 | Komplettgurt, Auffanggurt, Full Body | Gurte > Auffanggurt | 105 |
| 23 | Arbeitsgurt | Gurte > Arbeitsgurt | 5 |
| 24 | Sitzgurt, Klettergurt, Hüftgurt, Hochseilgartengurt, Industriegurt | Gurte > Sitzgurt | 80 |
| 25 | Brustgurt, Zubehörgürtel, Hüftgürtel, Sitzbrett, Fußstütze, Polsterung | Gurte > Zubehör Gurte | 26 |
| 26 | Falldämpferset | Verbindungsmittel > Verbindungsmittel mit Falldämpfer | 7 |
| 27 | Falldämpfer | Falldämpfer | 56 |
| 28 | Positionier… | Positionierung | 8 |
| 29 | Verbindungsmittel, Lanyard, Safety Chain | Verbindungsmittel | 140 |
| 30 | mitlaufendes Auffanggerät | Auffanggerät > Mitlaufende Auffanggeräte | 12 |
| 31 | Kletterhelm, Arbeitshelm, „Helm“ am Anfang | Helme > Schutzhelme | 121 |
| 32 | …rolle, Umlenkrolle, Seilrolle, Doppelrolle, Einzelrolle | Seilrollen | 76 |
| 33 | Schlinge, Tubular Band, Schlauchband | Schlingen & Lanyard | 131 |
| 34 | Steigklemme, Seilklemme, Ascender | Seilklemmen | 41 |
| 35 | Abseil… | Abseilgeräte | 45 |
| 36 | Sicherungsgerät, Bremsplatte | Sicherungsgeräte | 19 |
| 37 | Riggingplatte | Riggingplatten | 16 |
| 38 | Handschuh, Handschutz | Handschuhe | 62 |
| 39 | Tasche, Rucksack, Chalk Bag, Schlauchbeutel, Duffle | Taschen & Rucksäcke | 89 |
| 40 | Trage, Rettungstrage, Spineboard, Rettungsdreieck, Rettungssystem, Sked, Lift Sling, Splint | Rettung & Transport > Tragen | 33 |
| 41 | Dreibein, Tripod, Winde | Dreibein / Dreibein > Winde | 20 |
| 42 | Flaschenzug | Flaschenzüge | 7 |
| 43 | Anschlag…, Verankerung, Traverse, Spreizschraube, Spreizanker, Bohrhaken, Gerüsthaken, Fensterwagen, Halter, Anchor | Anschlageinrichtung | 36 |
| 44 | Seilschutz, Seilschoner | Seile > Seilschutz | 13 |
| 45 | Stirnlampe | Stirnlampe | 3 |
| 46 | Messer, Schneid… | Messer | 3 |
| 47 | Ersatzteil | Ersatzteil | 5 |

Die Regeln sind ein Startpunkt – Feinschliff beim Umsetzen (z. B. „NFC Chip für Statikseile“ ist Zubehör, kein Seil).

### 4.5 Edelrid (`2024-Edelrid-Produktliste_PL 2024_EU_26.09.2023_SAFETY_DRAFT.xlsx`)

- **Quelle:** Zwischenüberschrift-Zeilen der Preisliste (`DEUTSCH | ENGLISCH`, z. B. `STATIKSEILE | STATIC ROPES`). Produktzeilen: Spalte 1 Artikelnummer (Zahl), Spalte 2 Bezeichnung. Verknüpfung mit den Produkten in der Datenbank über die Artikelnummer.
- Die Überschrift gibt die Grundkategorie vor, Stichwörter im Namen korrigieren einzelne Produkte.

> Hinweis: Das vorhandene Edelrid-Mapping (`resources/import_mappings/edelrid.php`) liest eine CSV mit der Spalte `Einsatzbereich` (→ `pa_einsatzbereich`). Ob diese Spalte zusätzlich für Einsatzgebiete taugt, ist noch zu prüfen.

| Edelrid-Überschrift | Produkte | → Shop-Kategorie | Ausnahmen per Stichwort |
|---|---|---|---|
| STATIKSEILE | 38 | Seile > Statisch | – |
| SEILE MIT ENDVERBINDUNG | 5 | Seile > Statisch | (Aufpreis-Optionen) |
| REEPSCHNÜRE | 20 | Reepschnur | Throw Line, Retrieval Cone → Baumpflege-Zubehör (neu) · Hot Cutting Device → Messer |
| GURTE | 32 | Gurte (Unterkategorie je Produkt) | Spare…, Ersatz… → Gurte > Zubehör Gurte |
| HELME | 12 | Helme > Schutzhelme | Spare Padding, …Kit, Sticker → Helme > Zubehör Schutzhelme |
| VERBINDUNGSMITTEL | 47 | Verbindungsmittel | Shockstop → Verbindungsmittel mit Falldämpfer · Sling → Schlingen & Lanyard · Cambium Saver → Baumpflege-Zubehör (neu) |
| HARTWARE KARABINER | 92 | Karabiner > Alukarabiner | Steel → Karabiner > Stahlkarabiner |
| HARTWARE ZUBEHÖR | 8 | Ersatzteil | – |
| TRANSPORT | 15 | Taschen & Rucksäcke | – |
| ZUBEHÖR | 21 | Zubehör | Knife → Messer · Tool Safety Leash → Werkzeugsicherung (neu) · Corazon, Mini-O → Sicherungsgeräte |
| RFID Zubehör | 4 | RFID Tec | – |
| BEKLEIDUNG | 32 | **nicht importieren** | Ausnahme: Work Gloves, Grip Glove, Skinny Gloves → Handschuhe |
| MERCHANDISING | 11 | Werbemittel | – |

### 4.6 Singing Rock (eigene Liste)
- **Quelle:** `CATEGORIES` / `GROUP_CODE_NAME` – heute nur als Attribute gespeichert. Zuordnung noch offen. Singing Rock kommt zusätzlich über die Aliens-Liste (Abschnitt 4.4).

---

## 5. Einsatzgebiete

Bestehend: Baumpflege, Bergsteigen, Feuerwehr, Klettern, PSAgA, Spezialkräfte, Sport · **neu: Rettung, Segeln**

**Skylotec, Spalte 42 „Anwendungsgebiete“** (mehrere Werte, getrennt durch `,` `|` `/`):

| Skylotec | → Einsatzgebiete |
|---|---|
| Verschiedene | PSAgA |
| Rettung | Rettung, Feuerwehr, PSAgA, Spezialkräfte |
| Arbeiten an Masten und Türmen | PSAgA, Feuerwehr |
| Seilzugangstechnik | PSAgA, Feuerwehr, Spezialkräfte |
| Arbeiten in beengten Räumen | PSAgA, Rettung, Feuerwehr, Spezialkräfte |
| Gerüstbau | PSAgA |
| Baumpflege | Baumpflege, PSAgA |
| Taktischer Zugang | Spezialkräfte, Feuerwehr, Rettung |
| Arbeiten auf mobilen Plattformen | PSAgA, Feuerwehr, Spezialkräfte |
| Segel- und Jachtsport | Sport |
| Arbeiten auf Dach und Fassaden | PSAgA |

**Aliens:** Segeln-Produkte (Regel 11) → Einsatzgebiet Segeln.

---

## 6. Bilder

- Regel: **Nur Produkte mit Titelbild kommen in den Shop.** Varianten brauchen kein eigenes Bild.
- Die Aliens-Liste hat keine Bilder. **Bilder kommen aus dem Live-Shop:** über den Shop-Abgleich (Snapshot, Zuordnung per EAN/Artikelnummer) das Shop-Bild als Titelbild übernehmen.
- Für Produkte ohne Treffer: Liste „Bild fehlt“ (nach Marke). Weg zum Ergänzen noch offen (Bildarchive der Hersteller, Aliens, manuell).

---

## 7. Offene Punkte

- Behandlung `** NEU **`, `## DNV ##`, `-- SONDERANFERTIGUNG --` (Aliens)
- Zuordnung Singing Rock (eigene Liste)
- Edelrid: Spalte `Einsatzbereich` für Einsatzgebiete nutzen?
- Bildquelle für Produkte, die nicht im Live-Shop sind
- Aliens-Zeilen ohne passendes Produkt in der Datenbank: neu anlegen oder nicht? (Entscheidung nach dem ersten Probelauf)
- Shop-Import muss „Ausschließen“ und `online_sellable = false` (AUSVERKAUFT) beachten – heute gibt es dafür keinen Filter
