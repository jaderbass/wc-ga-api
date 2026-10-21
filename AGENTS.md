# GeoAlpin / PS-Alpin – Arbeitsregeln

## Projekt

- Laravel-Anwendung für Produktimporte und WooCommerce-Synchronisierung.
- Hersteller u. a. Aliens, Edelrid, Petzl, Kask und Skylotec.
- Bestehende Architektur und herstellerspezifische Importlogik respektieren.
- Vor Änderungen zuerst relevante bestehende Klassen, Tests und Konfigurationen lesen.

## Git

- Vor jeder Änderung `git status -sb` und den aktuellen Branch prüfen.
- Niemals ungefragt direkt auf `main` entwickeln.
- Keine vorhandenen Commits umschreiben.
- Kein `git reset --hard`, `git clean`, Force-Push oder Branch-Löschen ohne ausdrücklichen Auftrag.
- Keine Deployments oder Produktionsmigrationen ohne ausdrücklichen Auftrag.
- Vor einem Push prüfen, ob Remote-Änderungen vorliegen.
- Standardmäßig nicht selbst committen oder pushen, sofern der Auftrag das nicht ausdrücklich verlangt.

## Tests und Code-Stil

- Nach Änderungen `git diff --check` ausführen.
- Laravel Pint nur für relevante bzw. geänderte PHP-Dateien verwenden.
- Nicht den kompletten historisch nicht Pint-sauberen Bestand als Blocker behandeln.
- Relevante Tests ausführen.
- Bei Änderungen an Importern möglichst die herstellerspezifischen Tests bzw. betroffenen Testgruppen ausführen.
- Am Ende `git status -sb` prüfen.

## Datenbank und Produktion

- Tests dürfen niemals gegen die Produktionsdatenbank laufen.
- Ein exportiertes `DB_PASSWORD` kann die `.env` überschreiben.
- Vor Produktions-Artisan-Befehlen sicherstellen, dass keine Test-DB-Umgebungsvariablen aktiv sind.
- Keine destruktiven Datenbankaktionen ohne ausdrückliche Freigabe.
- Keine Produktionsmigrationen automatisch ausführen.

## Secrets und Zugangsdaten

- Keine Passwörter, Tokens, API-Schlüssel oder sonstigen Secrets in Code, Tests, Commits oder Logs aufnehmen.
- Secrets gehören in Umgebungsvariablen bzw. geeignete lokale Konfiguration.
- Persönliche Zugangsdaten nicht als Defaultwerte in Seeder oder Konfigurationen hinterlegen.

## Arbeitsweise

- Bestehenden Code zuerst lesen, dann ändern.
- Keine Architektur-Neuschreibung, wenn eine kleine Erweiterung der bestehenden Struktur genügt.
- Bei fachlich unklaren Regeln nicht raten, sondern Rückfrage stellen.
- Vor größeren Änderungen kurz beschreiben, welcher Ansatz gewählt wird.
- Änderungen möglichst klein und thematisch fokussiert halten.
- Mit dem Benutzer auf Deutsch kommunizieren.
- Statusmeldungen, Zusammenfassungen, Rückfragen und Erklärungen auf Deutsch ausgeben.
- Technische Bezeichner, Klassen-, Methoden-, Datei- und Feldnamen unverändert lassen.
- Commit-Messages weiterhin auf Englisch formulieren, sofern nicht ausdrücklich anders gewünscht.
- Laufende Statusmeldungen kurz und sachlich halten; keine langen Erklärungen während der Ausführung.

## Abschluss einer Aufgabe

Am Ende kurz nennen:

- welche Dateien geändert wurden,
- was fachlich geändert wurde,
- welche Tests und Checks gelaufen sind,
- ob noch offene Punkte oder Unsicherheiten bestehen.

Nicht unnötig komplette Dateien oder lange Terminal-Ausgaben wiederholen.