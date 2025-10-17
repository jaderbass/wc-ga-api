# ⚙️ .env Setup (GeoAlpin)

Beispielkonfiguration für lokale und produktive Umgebungen.

```dotenv
APP_NAME="GeoAlpin Sync"
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=geoalpin
DB_USERNAME=root
DB_PASSWORD=secret

# WooCommerce API
WOO_API_BASE_URL=https://shop.geoalpin.eu/wp-json/wc/v3
WOO_API_KEY=ck_xxxxxxxxxxxxxxxxxxxxxxx
WOO_API_SECRET=cs_xxxxxxxxxxxxxxxxxxx
WOO_SYNC_ENABLED=true

# Optional
WOO_TIMEOUT=30
WOO_RETRY=2
QUEUE_CONNECTION=sync
CACHE_DRIVER=file
SESSION_DRIVER=file
```
---
Tipps:
- Für lokale Tests: `APP_ENV=local`, `QUEUE_CONNECTION=sync`
- Für Produktion: `APP_DEBUG=false`, `LOG_LEVEL=info`
- Nach Änderungen: `php artisan optimize:clear`
