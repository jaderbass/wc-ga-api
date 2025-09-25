# Datenbank-Schema & Neuinstallation

Dieses Projekt nutzt **Laravel Schema Dump**, um die Datenbankstruktur sauber bereitzustellen, ohne dass alle einzelnen Migrationen durchlaufen werden müssen.

## 1. Neue Datenbank anlegen

```sql
CREATE DATABASE wc_ga_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. `.env` konfigurieren

Die DB-Verbindungsdaten (`DB_*`) in der `.env` anpassen.  
Falls ein Passwort Sonderzeichen wie `!` enthält, **immer in Quotes setzen**:

```dotenv
DB_PASSWORD="MeinPasswort!123"
```

## 3. Schema-Dump laden

Beim ersten `php artisan migrate` wird automatisch die Datei  
`database/schema/mysql-schema.sql` eingespielt.  
Damit steht sofort das vollständige Schema zur Verfügung.

```bash
php artisan migrate
```

## 4. Neue Migrationen

Alle Migrationen, die **nach** dem letzten Dump erstellt werden, laufen ganz normal zusätzlich.  
Beispiel:

```bash
php artisan make:migration add_new_field_to_products_table
php artisan migrate
```

## 5. Schema aktualisieren (aufräumen)

Wenn viele neue Migrationen dazugekommen sind und man wieder einen sauberen Stand haben möchte:

```bash
php artisan schema:dump --prune
```

- Erstellt `database/schema/mysql-schema.sql` neu (aktueller Stand).  
- Löscht alle bisherigen Migrationen im `database/migrations`-Ordner.  
- Bestehende Daten bleiben unverändert.

## 6. Unterschied zwischen `mysql` und `mysql_dump`

- **`mysql`**: Die reguläre Connection, die Laravel im Alltag verwendet (App, Migrations, Queries).  
- **`mysql_dump`**: Eine separate Connection, die nur für den Schema-Dump gedacht ist.  
  - Nutzer mit Minimalrechten (`SELECT`, `SHOW VIEW`, `TRIGGER`, `LOCK TABLES`).  
  - Vorteil: Der App-User braucht keine unnötigen Rechte.  
  - Beim Dump erzeugt Laravel dann die Datei `database/schema/mysql-schema.sql`.

> Hinweis: Standardmäßig verwendet `php artisan migrate` nur `mysql`. Der `mysql_dump`-User kommt ausschließlich bei `php artisan schema:dump` zum Einsatz.

## 7. Wichtig für Team/CI

- Immer die Datei `database/schema/mysql-schema.sql` ins Git committen.  
- Damit können neue Entwickler und CI/CD-Pipelines sofort eine lauffähige DB-Struktur anlegen, ohne hunderte Migrationen durchlaufen zu müssen.
