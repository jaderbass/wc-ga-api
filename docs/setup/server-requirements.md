# 🌐 Server-Voraussetzungen (GeoAlpin)

Diese Datei listet die minimalen und empfohlenen Serveranforderungen für den Betrieb
der Laravel/Filament-Anwendung (GeoAlpin Sync).

| Komponente | Mindestversion | Empfehlung |
|-------------|----------------|-------------|
| PHP | 8.3 | 8.3.x mit `curl`, `intl`, `xml`, `mbstring`, `bcmath` |
| MySQL | 8.0 | 8.4 |
| Webserver | Apache 2.4 / Nginx 1.18 | Nginx 1.22 mit PHP-FPM |
| Composer | 2.7 | 2.7+ |
| Node.js | 20 | 20+ |
| Speicher | 512 MB | ≥ 2 GB |
| Cron / Queue | aktiviert | erforderlich für automatischen Sync |

### Zusätzliche PHP-Extensions
```
pdo_mysql
openssl
tokenizer
fileinfo
xml
mbstring
curl
intl
json
```
### Sicherheit
- HTTPS zwingend empfohlen
- `APP_ENV=production` + `APP_DEBUG=false` im Livebetrieb
- Schreibrechte für `storage/` und `bootstrap/cache/`

---
© JAderBass web’n’more – Stand 2025-10-17
