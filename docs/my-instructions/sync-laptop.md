# 🧳 Projekt auf dem Laptop aktualisieren (Git & Laravel)

Diese Anleitung hilft dir, dein bestehendes Laravel-Projekt auf deinem Laptop mit dem aktuellen Stand vom PC/Remote-Repo zu synchronisieren – inkl. Branchwechsel und Sicherung lokaler Änderungen.

## ✅ Voraussetzungen

- Projektverzeichnis existiert bereits auf dem Laptop
- .env-Datei ist vorhanden und korrekt
- Datenbank ist lokal einsatzbereit

## 1. Öffne das Terminal und wechsle ins Projektverzeichnis

`cd /pfad/zum/deinem/projekt`

## 2. Prüfe, ob du ungesicherte Änderungen hast

`git status`

Wenn Änderungen angezeigt werden, aber du sie nicht verlieren willst:

`git stash`

(Damit sicherst du sie zwischen.)

## 3. Lade alle Branches vom Remote (z. B. GitHub)

`git fetch origin`

## 4. Aktualisiere den Hauptbranch main

```bash
git checkout main
git pull origin main
```

## 5. Remote-Branch lokal auschecken (wenn noch nicht vorhanden)

Wenn du z. B. am Branch `feature/product-variations` weiterarbeiten willst:

`git checkout -b feature/product-variations origin/feature/product-variations`

Falls der Branch lokal bereits existiert:

```bash
git checkout feature/product-variations
git pull origin feature/product-variations
```

## 6. Starte Laravel wie gewohnt

`php artisan serve`

oder deine übliche Umgebung (z. B. Valet, Laragon etc.)

## 7. Änderungen später wieder pushen

Wenn du im Zug arbeitest und Änderungen machst:

```bash
git add .
git commit -m "Meine Änderungen vom Laptop"
git push origin feature/product-variations
```

## 🔍 Optional: Überblick über alle Branches

`git branch -a`

(Zeigt dir alle lokalen und Remote-Branches)

## ✅ Hinweis zu Composer

Falls du das Projekt auf einem frischen System öffnest:

```bash
composer install
cp .env.example .env
php artisan key:generate
```
