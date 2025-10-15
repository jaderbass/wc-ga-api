# Produkte per CLI synchronisieren

## Cloudflared-Tunnel erstellen (optional)

`php artisan tunnel:quick`

1. `php artisan optimize:clear`
2. `php artisan woo:ping` - Verbindung testen
3. `php artisan -vvv woo:sync:product --ids=<ID> --no-interaction` - Parent- oder Einfaches Produkt synchronisieren
4. `php artisan woo:sync:variations --ids=<ID> --no-interaction` - Variationen synchronisieren
