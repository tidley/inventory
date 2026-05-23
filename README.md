# Personal Inventory

Small PHP-hosted, PWA-capable personal inventory tracker with MySQL storage.

## Files

- `index.php` serves the mobile-first UI.
- `api.php` stores and searches inventory rows in MySQL.
- `photo.php` serves item photos stored in MySQL.
- `auth.php` and `auth.js` provide username/PIN login and optional device-unlock passkey sign-in.
- `updater.php` installs GitHub release ZIPs when `UPDATE_TOKEN` is configured.
- `manifest.json`, `sw.js`, and `icons/` make the app installable on a phone.
- `styles.css` and `app.js` are static assets.
- `.env` contains the database credentials and is ignored by git.

## Deploy

Copy the files to PHP hosting with the PHP PDO MySQL extension enabled. The app creates or updates its `inventory_items` table on first API use, including the columns used for database-backed item photos.

For cPanel Git Version Control, `.cpanel.yml` deploys to `$HOME/public_html/inventory/`. If the subdomain document root is different, update `DEPLOYPATH` in `.cpanel.yml` before deploying.

Create `.env` from `.env.example` and set:

- Database: your MySQL database name, for example `example_inventory`
- User: your MySQL database username, for example `example_inventory_user`
- Host: `localhost`
- Update token: a long random value used by the in-app updater.
- Update repository: the GitHub `owner/repository` used for release updates.
- GitHub token: optional, only needed if the release repository is private.
- Auth username: for example `inventory-admin`
- Auth PIN hash: create with `php -r 'echo password_hash("your-pin", PASSWORD_DEFAULT), PHP_EOL;'`

Use `AUTH_PIN_HASH` in production. `AUTH_PIN` is supported as a simpler fallback, but it stores the PIN in plain text in `.env`.

cPanel may add an account prefix to database and user names. If it does, update `.env` to match the exact names shown in cPanel's MySQL Databases page.

The app creates/updates its own table on first use, so the database user needs `CREATE` and `ALTER` for initial setup. After the table exists, normal use only needs `SELECT`, `INSERT`, `UPDATE`, and `DELETE`.

For a local check:

```sh
php -S 127.0.0.1:8080
```

## Releases

Create a tag like `v0.3.10` after updating `INVENTORY_VERSION` in `version.php`. GitHub Actions builds `build/inventory.zip` and attaches it to the release. The in-app update panel checks the repository configured by `INVENTORY_UPDATE_REPO` and installs the ZIP when the configured `UPDATE_TOKEN` is supplied. For a private GitHub repository, set `INVENTORY_GITHUB_TOKEN` in `.env` to a token that can read repository contents/releases.
