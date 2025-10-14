# 🧭 Git Commit Guidelines – GeoAlpin Projekt

## 🎯 Ziel

Diese Richtlinie stellt sicher, dass alle Commits:

- logisch zusammenhängende Änderungen kapseln,
- sauber nachvollziehbar bleiben (Git-History lesbar),
- jederzeit revertierbar oder cherry-pick-bar sind,
- und automatisierte Deployments (später) nicht behindern.

---

## 🧩 1. Wann committen?

Committe immer dann, wenn **eine dieser Bedingungen erfüllt** ist:

| Zeitpunkt | Beispiel |
|:--|:--|
| ✅ **Neue Funktion** | Neue Klasse wie `WooAttributeResolver`, neue Artisan-Commands, neue Filament-Actions |
| ✅ **Fehlerbehebung** | Ein Logikfehler, Exception-Fix, falscher Rückgabewert korrigiert |
| ✅ **Refactoring** | Code aufgeräumt, Umbenennungen, Strukturänderungen ohne Funktionsänderung |
| ✅ **Dokumentation** | README, How-To, interne `docs/*.md` aktualisiert |
| ✅ **Sicherungs-Commit** | Nach mehreren erfolgreichen Tests oder vor riskanten Experimenten |
| 🚫 **Nicht committen:** halbfertige oder nicht getestete Funktionen, Dumps, Debug-Logs, .env-Dateien |

💡 **Faustregel:**  
> *„Ein Commit = eine logische Änderung, die ich mit einem Satz erklären kann.“*

---

## 🏷️ 2. Commit-Message-Format

Verwende das **konventionelle Format**:
