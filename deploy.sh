#!/usr/bin/env bash
#
# deploy.sh — Zero-downtime-freundliches Deploy für Laravel 12 auf Hetzner Managed Server
#
# Verwendung:
#   ./deploy.sh                 # benutzt aktuellen Branch
#   ./deploy.sh origin main     # zieht explizit von origin/main
#
# Hinweise:
# - Erwartet PHP 8.4 in der CLI und die Extensions: pdo_mysql, intl, fileinfo, zip
# - Führt KEINE Migrationen aus. (Optionalen Block unten aktivieren, wenn gewünscht.)
# - Nutzt config:cache / route:cache. Falls das bei dir (noch) Probleme macht, diese beiden Zeilen auskommentieren.

set -euo pipefail

## ──────────────────────────────────────────────────────────────────────────────
## Einstellungen
## ──────────────────────────────────────────────────────────────────────────────
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

# Git-Remote/Ref aus Parametern (optional)
GIT_REMOTE="${1:-}"
GIT_REF="${2:-}"

## ──────────────────────────────────────────────────────────────────────────────
## Helper
## ──────────────────────────────────────────────────────────────────────────────
say() { printf "\n\033[1;36m› %s\033[0m\n" "$*"; }
die() { printf "\n\033[1;31m✖ %s\033[0m\n" "$*"; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Benötigtes Kommando nicht gefunden: $1"
}

## ──────────────────────────────────────────────────────────────────────────────
## Vorab-Checks
## ──────────────────────────────────────────────────────────────────────────────
cd "$PROJECT_ROOT"

require_cmd "$PHP_BIN"
require_cmd "$COMPOSER_BIN"
require_cmd git

say "PHP-Version / ini:"
$PHP_BIN -v | head -n 2
$PHP_BIN -i | grep -E 'Loaded Configuration File|Configuration File \(php.ini\) Path' || true

say "PHP-Module (Kurzcheck):"
$PHP_BIN -m | grep -E 'pdo_mysql|intl|fileinfo|zip' || true

## ──────────────────────────────────────────────────────────────────────────────
## App in Maintenance (minimal)
## ──────────────────────────────────────────────────────────────────────────────
say "App in Maintenance-Mode versetzen"
$PHP_BIN artisan down || true

## ──────────────────────────────────────────────────────────────────────────────
## Git aktualisieren
## ──────────────────────────────────────────────────────────────────────────────
if [[ -n "$GIT_REMOTE" && -n "$GIT_REF" ]]; then
  say "Git: fetch & pull ($GIT_REMOTE $GIT_REF)"
  git fetch "$GIT_REMOTE" "$GIT_REF"
  git checkout -q "$(echo "$GIT_REF" | sed 's#refs/heads/##')"
  git pull --ff-only "$GIT_REMOTE" "$(echo "$GIT_REF" | sed 's#refs/heads/##')"
else
  CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
  say "Git: pull aktuellen Branch ($CURRENT_BRANCH)"
  git pull --ff-only || true
fi

## ──────────────────────────────────────────────────────────────────────────────
## Composer (ohne dev, optimierter Autoloader)
## ──────────────────────────────────────────────────────────────────────────────
say "Composer install (no-dev, optimized autoloader)"
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction

## ──────────────────────────────────────────────────────────────────────────────
## Rechte & Verzeichnisse
## ──────────────────────────────────────────────────────────────────────────────
say "Rechte reparieren (storage, bootstrap/cache)"
mkdir -p storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
find storage -type d -exec chmod 775 {} \; 2>/dev/null || true
find storage -type f -exec chmod 664 {} \; 2>/dev/null || true
chmod 775 bootstrap/cache

## ──────────────────────────────────────────────────────────────────────────────
## Symlink für Storage (idempotent)
## ──────────────────────────────────────────────────────────────────────────────
say "Storage symlink prüfen/anlegen"
$PHP_BIN artisan storage:link || true

## ──────────────────────────────────────────────────────────────────────────────
## Caches: erst hart bereinigen, dann sauber neu aufbauen
## ──────────────────────────────────────────────────────────────────────────────
say "Caches bereinigen"
rm -f bootstrap/cache/config.php bootstrap/cache/packages.php bootstrap/cache/routes-*.php || true

say "optimize:clear"
$PHP_BIN artisan optimize:clear || true

say "config:cache"
$PHP_BIN artisan config:cache

say "route:cache"
$PHP_BIN artisan route:cache

say "view:cache (optional, schnell)"
$PHP_BIN artisan view:cache || true

## ──────────────────────────────────────────────────────────────────────────────
## (Optional) Migrations – nur aktivieren, wenn gewünscht
## ──────────────────────────────────────────────────────────────────────────────
# say "Datenbank-Migrationen ausführen"
# $PHP_BIN artisan migrate --force

## ──────────────────────────────────────────────────────────────────────────────
## App wieder hochfahren
## ──────────────────────────────────────────────────────────────────────────────
say "App wieder online nehmen"
$PHP_BIN artisan up || true

## ──────────────────────────────────────────────────────────────────────────────
## Kurzer Gesundheitscheck
## ──────────────────────────────────────────────────────────────────────────────
say "Healthcheck: artisan about (Kurzfassung)"
$PHP_BIN artisan about | head -n 30 || true

say "Healthcheck: Routes (Filament)"
$PHP_BIN artisan route:list --name=filament | head -n 20 || true

say "Deployment abgeschlossen ✅"
