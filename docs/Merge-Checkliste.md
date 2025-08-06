# Merge-Checkliste: Feature-Branch in `main` übernehmen

Diese Checkliste beschreibt die Schritte, um einen Feature-Branch (z. B. `feature/woocommerce-sync`) sauber in den `main`-Branch zu mergen und danach einen neuen Branch für die nächste Entwicklung anzulegen.

---

## 1. Lokale Arbeitsumgebung aktualisieren
```bash
git checkout main
git fetch origin
git pull origin main
```

**Zweck:** Sicherstellen, dass dein main-Branch aktuell ist.

## 2. Zum Feature-Branch wechseln

`git checkout feature/woocommerce-sync`

**Zweck:** Den Branch mit den neuen Änderungen auswählen.

## 3. Merge in `main` vorbereiten

```bash
git checkout main
git merge feature/woocommerce-sync
```

**Zweck:** Führt die Änderungen des Feature-Branches in den `main`-Branch zusammen.

## 4. Konflikte prüfen & lösen

Falls Git meldet:

```pgsql
CONFLICT (content): Merge conflict in <file>
```

- Öffne die betroffenen Dateien.
- Löse die Konflikte (Git markiert sie mit `<<<<<<<` und `>>>>>>>`).
- Danach:

```bash
git add <file>
git commit
```
## 5. Testlauf starten

Bevor du pushst:

```bash
php artisan migrate
php artisan test
```

**Zweck:** Sicherstellen, dass die Migrations & Tests erfolgreich laufen.

## 6. Merge pushen

```bash
git push origin main
```

**Zweck:** Dein `main` ist jetzt auf dem neuesten Stand.

## 7. Neuen Branch für Produktvarianten erstellen

```bash
git checkout -b feature/product-variations
git push origin feature/product-variations
```

**Zweck:** Neuer Arbeitszweig für die Entwicklung von Produktvarianten.

## Optional: Safety-Check

```bash
git branch --merged main
```

**Zweck:** Prüfen, ob der alte Feature-Branch bereits vollständig in main gemergt ist.

---

Willst du, dass ich dir noch ein **Git-Shortcut-Skript** schreibe (z. B. `merge-feature.sh`), das diese Schritte halbautomatisch macht?  
Dann könntest du einen Merge mit **einem einzigen Befehl** starten. Soll ich das auch bauen?
