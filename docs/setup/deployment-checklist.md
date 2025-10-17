# 🚀 Deployment-Checkliste (GeoAlpin)

Vor dem Livegang oder Update bitte folgende Punkte durchgehen:

## ✅ Vorbereitung
- [ ] `git pull` → aktueller Stand
- [ ] `.env` mit Livewerten befüllt (keine lokalen Keys!)
- [ ] `composer install --no-dev`
- [ ] `npm run build`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache`
- [ ] Queue-Worker gestartet (`php artisan queue:work`)

## 🔐 Sicherheit
- [ ] `APP_DEBUG=false`
- [ ] HTTPS aktiv
- [ ] Verzeichnisrechte: `storage/` & `bootstrap/cache/` = beschreibbar
- [ ] Keine `.env` oder `/tests/` öffentlich zugänglich

## 🧪 Tests
- [ ] `php artisan app:woo-sync-product --dry`
- [ ] `php artisan app:woo-sync-variations --dry`
- [ ] Log prüfen (`storage/logs/laravel.log`)
- [ ] Frontend auf 404/500 prüfen

## 📋 Nachbereitung
- [ ] `php artisan schedule:run` (falls Cron)
- [ ] Backup prüfen
- [ ] Tag setzen (`git tag -a vX.Y.Z -m "Deployment"`)

---
© JAderBass web’n’more · Stand 2025-10-17
