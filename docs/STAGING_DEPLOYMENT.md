# Aruba staging deployment

This procedure targets an Aruba Easy Linux shared-hosting directory served at
`https://www.dev-sandbox.it/iaslab-projects/`. It requires SFTP and phpMyAdmin only;
no server shell or server-side Composer command is needed.

## Requirements

- PHP 8.3 with `pdo`, `pdo_mysql`, `mbstring`, `session`, `json`, `filter`, and `date`
- Apache `mod_rewrite`, `.htaccess` overrides, and HTTPS
- MySQL/MariaDB with InnoDB and `utf8mb4`
- SFTP credentials and phpMyAdmin access

The repository includes a root front controller and `.htaccess`. The latter serves
files from `public/assets`, routes application requests to `index.php`, disables
directory indexes, and denies HTTP access to source, configuration, database,
storage, test, and dependency directories.

## Build locally

From a clean release checkout, using PHP 8.3 and Composer:

```bash
composer install --no-dev --prefer-dist
composer dump-autoload -o
```

The application has no runtime Composer operation. Upload the generated `vendor/`
directory with the release even though application classes also have a small
bootstrap autoloader.

Copy `.env.example` to `.env` locally and configure it before upload, or upload the
example and rename/edit it through the hosting file manager. Never upload a
developer `.env`.

## Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Use `staging`; debug output and development-only routes are disabled. |
| `APP_DEBUG` | Use `false` on staging. Staging suppresses debug output even if this is accidentally true. |
| `APP_URL` | Origin only, for example `https://www.dev-sandbox.it`. |
| `APP_BASE_PATH` | URL installation path, `/iaslab-projects`. |
| `APP_CLEAN_URLS` | `true` with working rewrite rules; use `false` only as the documented fallback. |
| `APP_TIMEZONE` | Application timezone, normally `Europe/Rome`. |
| `APP_SESSION_NAME` | Unique cookie/session name for this installation. |
| `APP_VERSION` | Non-secret deployed release label shown on the admin System page. |
| `APP_LOG_PATH` | Log file relative to the project root, normally `storage/logs/application.log`. |
| `SESSION_IDLE_TIMEOUT` | Idle session lifetime in seconds. |
| `SESSION_ABSOLUTE_TIMEOUT` | Maximum session lifetime in seconds. |
| `PASSWORD_MIN_LENGTH` | Minimum password length. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Aruba MySQL values. |

`APP_BASE_URL` remains accepted as a compatibility alias, but new deployments
should use `APP_URL`. Existing process-level environment variables take precedence
over `.env`.

## Upload

1. Back up the staging directory and export the staging database.
2. Upload the complete release directory, including hidden `.htaccess`, `.env`,
   `vendor/`, `public/assets/`, and `storage/logs/`, into `/iaslab-projects`.
3. Do not put production credentials in `config/config.php`; `.env` is the staging
   configuration source.
4. Use `0755` for directories and `0644` for files where Aruba permits. Do not use
   `0777`.
5. Ensure `storage/logs` is writable by PHP. PHP's configured session directory
   must also be writable; the Administration → System page reports both.

There are no uploads or application caches in this milestone. The only
application-owned runtime write is `storage/logs/application.log`; PHP owns session
files in the host-configured session directory.

## Database import

In phpMyAdmin, select the empty staging database and import
`database/migrations/001_create_schema_versions.sql` through
`010_add_work_package_allocations.sql` in numeric order. For an existing database,
import only missing migrations after taking a backup. The administrator System page
compares the migration files shipped in the release with `schema_versions`.

Create the first administrator before upload using the local documented
`bin/create-admin.php` workflow against an appropriate database, or insert a
locally generated `PASSWORD_DEFAULT` hash through phpMyAdmin. Never store or paste
the plain password in SQL.

## First verification

Open `https://www.dev-sandbox.it/iaslab-projects/`, sign in, and visit
Administration → System. Confirm:

- PHP is 8.3;
- environment is `staging`;
- the displayed application URL ends in `/iaslab-projects`;
- database status is Connected;
- no migration is missing;
- the log and session directories are writable;
- Users without linked Person is zero.

Then verify logout, Overview, Projects, a project annual page, Edit hours, Capacity,
People, Users, and System. Browser network tools should show no asset `404`, every
redirect must remain below `/iaslab-projects`, and only intentionally scrollable
tables may overflow horizontally.

## Troubleshooting

- **404 on every route:** confirm `.htaccess` was uploaded and Aruba permits
  rewrite overrides. As a fallback set `APP_CLEAN_URLS=false`; generated links use
  `index.php?route=...`.
- **Assets return 404:** confirm `public/assets` was uploaded and the root
  `.htaccess` is present.
- **Redirect leaves the subdirectory:** confirm `APP_URL` contains only the HTTPS
  origin and `APP_BASE_PATH=/iaslab-projects`.
- **500 with a clean error page:** inspect `storage/logs/application.log` or the
  Aruba PHP error log. The browser intentionally receives no trace in staging.
- **Database unavailable:** verify Aruba's database host (it may not be
  `localhost`), port, database name, username, password, and PDO MySQL extension.
- **System reports missing migrations:** import only the listed SQL migrations in
  ascending order after a backup.
- **Log directory not writable:** correct `storage/logs` permissions using the
  hosting file manager; never expose it over HTTP or use world-writable mode.

Aruba control-panel behavior, rewrite availability, and the final filesystem
permissions must still be confirmed on the actual hosting account.
