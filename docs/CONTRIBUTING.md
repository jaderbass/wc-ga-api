# 🤝 CONTRIBUTING.md – GeoAlpin

Vielen Dank, dass Du zu GeoAlpin beitragen möchtest!  
Dieses Dokument erklärt, wie Änderungen, Verbesserungen oder Fehlerkorrekturen
effizient eingebracht werden können.

---

## 🔧 Entwicklungsumgebung

**Empfohlen:**
- PHP 8.3, Laravel 12, Filament 3.2
- MySQL ≥ 8.0
- Node.js 20
- Composer ≥ 2.7

**Setup:**
```bash
git clone https://github.com/jaderbass/wc-ga-api.git
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

---

## 🧩 Branching-Strategie

| Branch | Zweck |
|---------|-------|
| `main` | stabile Produktionsversion |
| `develop` | aktiver Entwicklungszweig |
| `feature/*` | neue Features oder Refactorings |
| `hotfix/*` | dringende Fehlerbehebungen |

Beispiel:
```bash
git checkout -b feature/woo-export-queue
```

---

## 🧱 Commits

Nutze **konventionelle Commit-Messages**:

| Typ | Bedeutung |
|------|------------|
| `feat` | neue Funktion |
| `fix` | Fehlerbehebung |
| `refactor` | Code-Verbesserung ohne neues Feature |
| `docs` | Dokumentation |
| `style` | Formatierung, keine Logikänderung |
| `test` | Tests oder QA |
| `chore` | allgemeine Wartung / Config |

**Beispiele:**
```
feat(sync): add orchestrator entry for variant sync
fix(import): handle missing EAN gracefully
docs(readme): add setup instructions
```

---

## 🧪 Tests & Qualität

Vor jedem Commit:
```bash
./vendor/bin/pint        # Code-Style
php artisan test         # Tests ausführen
php artisan migrate:fresh --seed
```

Ergänze neue Tests, wenn Du neue Services oder Actions einführst.

---

## 🔄 Pull Requests

1. Stelle sicher, dass Dein Branch aktuell ist (`git rebase develop`).
2. Füge eine klare Beschreibung hinzu (Zweck, Motivation, Änderungen).
3. Wenn möglich: Screenshots oder Logausgaben anhängen.
4. CI/CD-Lauf (GitHub Actions) sollte grün sein.

---

## 💬 Kommunikation

- Issues im Repo: präzise Beschreibung + Steps to Reproduce  
- Diskussionen / Ideen: im Tab „Discussions“  
- Bei größeren Features bitte zuerst kurz skizzieren (z. B. Orchestrator-Änderungen).

---

## 🧾 Lizenz & Rechte

Alle Beiträge unterliegen der Lizenz des Projekts (MIT).  
Mit Deinem Pull Request erklärst Du, dass Du die Rechte an Deinem Code besitzt
und dass er keine externen Schutzrechte verletzt.

---
© 2025 JAderBass web’n’more · Maintainer: Jörg Aderhold
