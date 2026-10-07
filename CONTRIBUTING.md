# 🧭 Beitrag- und Workflow-Richtlinien

## 🔹 Ziel

Dieses Dokument beschreibt die Vorgehensweise für Änderungen am Projekt GeoAlpin (`wc-ga-api`).
Es soll konsistente Commits, nachvollziehbare Branch-Strukturen und stabile Releases sicherstellen.

---

## Filament CSS / Vite-Build auf dem Server

Das Projekt verwendet benutzerdefinierte Filament-CSS-Overrides über:

```txt
resources/css/filament/admin-overrides.css
```

Die CSS-Datei wird per Vite gebaut und über den `AdminPanelProvider` eingebunden.

### Wichtige Besonderheit Server-Setup

Der Laravel-Projektpfad und der tatsächlich ausgelieferte Webroot unterscheiden sich:

```txt
Projekt:
 /usr/home/geoalp/wc-ga-api

Öffentlicher Webroot:
 /usr/www/users/geoalp/api
```

Daher reicht ein normales Builden nicht aus.

### Vorgehen bei CSS-Änderungen

Build erzeugen:

```bash
npm install
npm run build
```

Build in den aktiven Webroot kopieren:

```bash
rm -rf /usr/www/users/geoalp/api/build
cp -a /usr/home/geoalp/wc-ga-api/public/build /usr/www/users/geoalp/api/
```

Laravel-Caches leeren:

```bash
php artisan optimize:clear
```

Browser anschließend hart neu laden (`Strg + F5`).

### Typisches Fehlerbild

CSS-Dateien werden geändert und gebaut, Änderungen erscheinen jedoch nicht im Browser.

Ursache:

- Assets wurden nur unter `wc-ga-api/public/build` erstellt
- Webserver liefert jedoch Dateien aus `/usr/www/users/geoalp/api/build`

---

## 🧩 Branch-Strategie

- **`main`** → Stabile, getestete Version (nur über Pull Requests von Feature-Branches).
- **`develop`** *(optional)* → Integrations-Branch für parallele Feature-Entwicklung.
- **Feature-Branches**:
  Namensschema `feature/<bereich>-<kurzbeschreibung>`
  Beispiel: `feature/woo-export` oder `feature/import-xml-feeds`

Wenn du einen Bug fixst:
`fix/<bereich>-<problem>`
Beispiel: `fix/woo-name-mapping`

---

## 🪶 Commits

- **Commit-Sprache:** Englisch (kurz, prägnant)
- **Format:** `type(scope): message`
  Beispiele:

  - `feat(import): add XML feed support`
  - `fix(sync): correct name resolver fallback`
  - `chore(docs): update changelog for v0.4.3`

**Commit-Typen:**

- `feat` – neue Funktion
- `fix` – Bugfix
- `chore` – Build-, CI-, Doku- oder Meta-Änderungen
- `refactor` – Code-Reorganisation ohne neue Features
- `docs` – Dokumentationsänderungen
- `style` – Layout, CSS oder Formatierungen
- `test` – neue oder geänderte Tests

---

## 🧪 Code- & Qualitätsregeln

- Alle Klassen enthalten **DocBlocks** in Deutsch (kurze Beschreibung, Parameter, Rückgabewert).
- Logging immer mit `Log::info|debug|warning|error` – kein `\Log`.
- Preise werden **nicht** exportiert.
- Bei DB-Änderungen Migration + Baseline-Dump aktualisieren.
- Keine temporären `dd()`, `dump()` oder `var_dump()` im Commit.
- CSS-Anpassungen gehören in `resources/css/filament/admin-overrides.css`.

---

## 🧱 Pull-Requests

1. Lokale Tests & Code-Review durchführen.
2. Branch mit `feature/*` benennen.
3. `git rebase main` (keine Merge-Commits).
4. PR-Titel nach Commit-Schema (`feat:`, `fix:`, …).
5. `CHANGELOG.md` und ggf. `RELEASE_TEMPLATE.md` aktualisieren.
6. Reviewer (z. B. @JAderBass) zuweisen.

---

## 🏷️ Releases

1. Nach erfolgreichem Test neuen Tag erstellen
   `git tag -a vX.Y.Z -m "Kurzbeschreibung"`
2. Datei `docs/RELEASE_TEMPLATE.md` kopieren und ausfüllen.
3. `CHANGELOG.md` → neuen Abschnitt `[vX.Y.Z]` eintragen.
4. Push:
   `git push origin <branch>`
   `git push origin vX.Y.Z`
5. Auf GitHub Release anlegen und Notes einfügen.

---

## 🔐 Sicherheit & Daten

- Keine sensiblen Zugangsdaten (API-Keys, Passwörter) im Code oder in Logs.
- `.env` nicht committen.
- Neue Umgebungsvariablen immer in `.env.example` aufnehmen.

---

## 🧰 Entwicklungsumgebung

- **Framework:** Laravel 12 + Filament 3.2
- **PHP:** ≥ 8.3
- **Node:** ≥ 20 LTS
- **DB:** MariaDB / MySQL 8.x
- **Tools:** Laragon / Valet / Sail, VS Code mit Intelephense

---

## 🪜 Workflow-Checkliste

Vor jedem Push:

- [ ] `php artisan test` – alle Tests grün
- [ ] `php artisan migrate:fresh --seed` erfolgreich
- [ ] `npm run build` ohne Fehler
- [ ] Keine `dd()`/`dump()`/`echo` im Code
- [ ] `CHANGELOG.md` und Release-Notes aktualisiert
- [ ] Commit-Message validiert
- [ ] Branch vom aktuellen `main` abgeleitet

---

**Autor:** Jörg Aderhold – JAderBass web’n’more
**Projekt:** GeoAlpin (Laravel 12 + Filament 3 + WooCommerce API)
**Stand:** {date.today().isoformat()}
