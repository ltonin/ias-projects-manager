# Aruba shared-hosting deployment

## Verify first

In the control panel confirm PHP 8.2 (or the closest compatible version), PDO MySQL, MySQL/MariaDB version, `mod_rewrite`, `.htaccess`/`AllowOverride`, whether the document root can point to `public/`, HTTPS/proxy behavior, FTP versus FTPS/SFTP, database host/port, upload limits, writable paths, backups, and PHP error-log access.

## Prepare and upload

1. Back up the current web files and export the database from phpMyAdmin. Test that the export is readable.
2. Build a clean package locally. Include `app/`, `bootstrap/`, `config/config.example.php`, `database/`, `public/`, `storage/logs/.gitkeep`, `views/`, and `LICENSE`.
3. Exclude `.git/`, `.env`, `config/config.php`, `tests/`, `vendor/`, `composer.*`, `phpunit.xml`, Docker files, `docs/`, caches, and logs. Composer is not required in production.
4. Upload through the most secure supported protocol. Prefer configuring the domain/subdomain document root to the uploaded `public/` directory.
5. If Aruba cannot change the document root, place `public` contents in the web root and keep `app`, `bootstrap`, `config`, `database`, `storage`, and `views` above it. Adjust only the `dirname()` root expression in the deployed `index.php` to that verified layout. Do not expose those directories beneath the web root.
6. Copy `config.example.php` locally to `config.php`, set `environment=production`, `debug=false`, the HTTPS `base_url`, subdirectory `base_path` (empty at root), clean-URL capability, timezone, unique session name, and database credentials. Upload it directly; never commit it.
7. Make runtime files read-only where possible (typically files `0644`, directories `0755`). Only a deliberately used log/upload directory should be writable; never use `0777`.

## Database and smoke test

In phpMyAdmin, select the intended database, inspect and import `database/migrations/001_create_schema_versions.sql`, and confirm version `001`. For later releases apply only absent migrations in numeric order using [DATABASE.md](DATABASE.md).

Visit `/`, `/health`, an unknown URL, and a known URL with the wrong method. Confirm HTTPS, assets, subdirectory links, safe 404/405 behavior, and that `/csrf-test` returns 404 in production. If rewrite fails, set `clean_urls=false` and use `index.php?route=health`.

## Rollback

For a bad release, stop or limit access if possible, preserve diagnostics, restore the prior file backup, and restore the pre-migration database export when schema/data changed. File rollback alone is unsafe after an incompatible migration. Keep credentials outside backups shared with developers and rotate any exposed secret.
