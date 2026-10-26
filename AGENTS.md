# GeoAlpin / PS-Alpin – Arbeitsregeln

## Projekt

- Laravel-Anwendung für Produktimporte und WooCommerce-Synchronisierung.
- Hersteller u. a. Aliens, Edelrid, Petzl, Kask und Skylotec.
- Bestehende Architektur und herstellerspezifische Importlogik respektieren.
- Bei Änderungen an Produktnamen oder Kategorien `docs/Produktnamen-Regeln.md` und `docs/Kategorie-Zuordnung.md` lesen; dokumentierte Soll-Regeln und bekannte Implementierungslücken unterscheiden. Offene Regeln nur im beauftragten Umfang umsetzen.

## Git

- Auf `main` vor Änderungen einen Arbeitsbranch abstimmen; auf einem vorhandenen Arbeitsbranch weiterarbeiten.
- Branches nur wechseln oder mergen, wenn dies ausdrücklich Teil des Auftrags ist.
- Vorhandene Benutzeränderungen nicht überschreiben, zurücksetzen oder ungefragt in eigene Änderungen einbeziehen.
- Keine vorhandenen Commits umschreiben.
- Kein `git reset --hard`, `git clean`, Force-Push oder Branch-Löschen ohne ausdrücklichen Auftrag.
- Vor einem Push prüfen, ob Remote-Änderungen vorliegen.
- Standardmäßig nicht selbst committen oder pushen, sofern der Auftrag das nicht ausdrücklich verlangt.

## Tests und Code-Stil

- Laravel Pint nur für relevante bzw. geänderte PHP-Dateien verwenden.
- Nicht den kompletten historisch nicht Pint-sauberen Bestand als Blocker behandeln.
- Relevante Tests ausführen.
- Bei Änderungen an Importern möglichst die herstellerspezifischen Tests bzw. betroffenen Testgruppen ausführen.

## Datenbank und Produktion

- Tests dürfen niemals gegen die Produktionsdatenbank laufen.
- `phpunit.xml` sieht die MySQL-Testdatenbank `geoalp_test` vor; Feature-Tests verwenden über `tests/Pest.php` `RefreshDatabase` und können Datenbankinhalte zurücksetzen.
- Vor Tests die tatsächlich wirksame Datenbankverbindung prüfen: exportierte `DB_*`-Variablen (insbesondere `DB_PASSWORD`), `DB_URL` und gecachte Konfiguration berücksichtigen. Zugangsdaten dabei nicht ausgeben.
- Vor Produktions-Artisan-Befehlen sicherstellen, dass keine Test-DB-Umgebungsvariablen aktiv sind.
- Keine destruktiven Datenbankaktionen ohne ausdrückliche Freigabe.
- Deployments und Produktionsmigrationen nur mit ausdrücklichem Auftrag ausführen.
- WooCommerce-Syncs, Importläufe und Smoke-Kommandos mit Datenänderungen nur gegen ausdrücklich freigegebene Testziele ausführen. Die Befehle in `docs/Quick-Test-Checklist.md` können Woo-IDs verändern und Produkte im Shop anlegen; sie sind keine rein lesenden Checks.

## Secrets und Zugangsdaten

- Keine Passwörter, Tokens, API-Schlüssel oder sonstigen Secrets in Code, Tests, Commits oder Logs aufnehmen.
- Secrets gehören in Umgebungsvariablen bzw. geeignete lokale Konfiguration.
- Persönliche Zugangsdaten nicht als Defaultwerte in Seeder oder Konfigurationen hinterlegen.
- Bereits vorhandene Secrets weder in Terminalausgaben noch in Berichten wiedergeben. Dateien und Logs gezielt lesen und sensible Werte vor der Ausgabe ausblenden; Fundstellen ohne Secret-Wert als separaten sicherheitsrelevanten offenen Punkt melden.

## Arbeitsweise

- Keine Architektur-Neuschreibung, wenn eine kleine Erweiterung der bestehenden Struktur genügt.
- Änderungen möglichst klein und thematisch fokussiert halten; bestehende Architektur und Konventionen verwenden.
- Mit dem Benutzer auf Deutsch kommunizieren, einschließlich Statusmeldungen, Zusammenfassungen, Rückfragen und Erklärungen.
- Technische Bezeichner, Klassen-, Methoden-, Datei- und Feldnamen unverändert lassen.
- Commit-Messages weiterhin auf Englisch formulieren, sofern nicht ausdrücklich anders gewünscht.
- Laufende Statusmeldungen kurz und sachlich halten; keine langen Erklärungen während der Ausführung.

## Abschluss einer Aufgabe

Am Ende kurz nennen:

- welche Dateien geändert wurden,
- was fachlich geändert wurde,
- welche Tests und Checks mit welchem Ergebnis gelaufen sind,
- ob noch offene Punkte oder Unsicherheiten bestehen.

Nicht unnötig komplette Dateien oder lange Terminal-Ausgaben wiederholen.

## Standard-Arbeitsablauf für Coding-Aufgaben

Bei jeder Coding-Aufgabe:

1. Vor Änderungen `git status -sb` und den aktuellen Branch prüfen sowie relevante bestehende Klassen, Tests und Konfigurationen lesen. Die Vorgaben im Abschnitt „Git“ beachten.
2. Nur bei größeren oder nicht offensichtlichen Aufgaben vor Änderungen kurz das Verständnis, die voraussichtlich betroffenen Dateien und den Ansatz auf Deutsch zusammenfassen.
3. Bei fachlich oder technisch mehrdeutigen Anforderungen nicht raten, sondern eine gezielte Rückfrage stellen; die davon betroffene Implementierung erst nach Klärung beginnen.
4. Den Auftrag nach den Abschnitten „Arbeitsweise“ und „Umfang von Aufträgen“ umsetzen.
5. Nach Änderungen `git diff --check` ausführen und die passenden Tests und Formatierungsprüfungen gemäß „Tests und Code-Stil“ unter Beachtung von „Datenbank und Produktion“ durchführen. Bei reinen Dokumentationsänderungen sind keine Anwendungstests oder Pint-Läufe erforderlich.
6. Abschließend `git status -sb` prüfen und gemäß „Abschluss einer Aufgabe“ berichten.

## Umfang von Aufträgen

Wenn ein Auftrag aus diesem Chat übernommen wurde, genau diesen Umfang umsetzen.

Wenn während der Arbeit weitere sinnvolle Änderungen auffallen:
- nicht automatisch mit erledigen
- kurz als separaten offenen Punkt melden
