# Personal Inventory

Small PHP-hosted, PWA-capable personal inventory tracker with MySQL storage.

## Files

- `index.php` serves the mobile-first UI.
- `api.php` stores and searches inventory rows in MySQL.
- `photo.php` serves item photos stored in MySQL.
- `manifest.json`, `sw.js`, and `icons/` make the app installable on a phone.
- `styles.css` and `app.js` are static assets.
- `.env` contains the database credentials and is ignored by git.

## Deploy

Copy the files to PHP hosting with the PHP PDO MySQL extension enabled. The app creates or updates its `inventory_items` table on first API use, including the columns used for database-backed item photos.

For cPanel Git Version Control, `.cpanel.yml` deploys to `$HOME/public_html/inventory/`. If the subdomain document root is different, update `DEPLOYPATH` in `.cpanel.yml` before deploying.

Create `.env` from `.env.example` and set:

- Database: `example_inventory`
- User: `example_inventory_user`
- Host: `localhost`

cPanel may add an account prefix to database and user names. If it does, update `.env` to match the exact names shown in cPanel's MySQL Databases page.

The app creates/updates its own table on first use, so the database user needs `CREATE` and `ALTER` for initial setup. After the table exists, normal use only needs `SELECT`, `INSERT`, `UPDATE`, and `DELETE`.

For a local check:

```sh
php -S 127.0.0.1:8080
```
