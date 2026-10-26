# Codex-Workflow

## Ziel

Aufgaben klar abstimmen, direkt im Repository umsetzen und mit passenden Checks nachvollziehbar abschließen. Verbindliche Regeln stehen in [AGENTS.md](../AGENTS.md).

## Aufgabenverteilung

- **Browserchat:** Ziele, fachliche Fragen und Lösungsansätze besprechen; daraus einen klar abgegrenzten Auftrag formulieren.
- **VS Code/Codex:** Den Auftrag am aktuellen Repository prüfen, relevante Dateien lesen, Änderungen umsetzen und Checks ausführen. Unklare Anforderungen vor der betroffenen Implementierung klären.
- **Übergabe:** Ziel, Umfang, Einschränkungen und wichtige Entscheidungen kurz mitgeben. Codex liest den aktuellen Code selbst; vollständige Dateien oder lange Terminalausgaben müssen nicht zwischen Browserchat und IDE kopiert werden.

## Standardablauf

1. `git status -sb` und Branch prüfen, vorhandene Benutzeränderungen schützen und relevante Dateien lesen. Auf `main` vor Änderungen einen Arbeitsbranch abstimmen.
2. Bei größeren oder nicht offensichtlichen Aufgaben Verständnis, voraussichtlich betroffene Bereiche und Ansatz kurz zusammenfassen.
3. Nur den beauftragten Umfang umsetzen, bestehende Architektur beachten und zusätzliche Funde separat melden.
4. `git diff --check` und relevante Tests ausführen; Pint auf betroffene PHP-Dateien begrenzen. Vor Datenbanktests die wirksame Testverbindung prüfen, niemals gegen Produktion testen. Reine Dokumentationsänderungen benötigen keine Anwendungstests oder Pint-Läufe.
5. Abschließend `git status -sb` prüfen und auf Deutsch kurz berichten: geänderte Dateien, fachliche Änderung, Checks mit Ergebnis und offene Punkte. Dateiverweise und gezielte Ausschnitte statt kompletter Dateien oder langer Logs verwenden; Secrets niemals ausgeben.

## Commit und Push

Commit und Push erfolgen standardmäßig manuell durch den Benutzer. Codex übernimmt sie nur auf ausdrücklichen Auftrag. Vor einem Push auf Remote-Änderungen prüfen; Commit-Messages grundsätzlich auf Englisch formulieren. Vor einem Push sicherstellen, dass der lokale Branch nicht hinter dem Remote liegt.
