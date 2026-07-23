# Aruba shared-hosting deployment

For the SFTP-only Milestone 12 staging procedure, use
[STAGING_DEPLOYMENT.md](STAGING_DEPLOYMENT.md). It supersedes the older packaging
and configuration steps below: the staging package includes `vendor/`, uses the
root front controller and `.htaccess`, and is configured by `.env`.

## Verify first

In the control panel confirm PHP 8.2 (or the closest compatible version), PDO MySQL, MySQL/MariaDB version, `mod_rewrite`, `.htaccess`/`AllowOverride`, whether the document root can point to `public/`, HTTPS/proxy behavior, FTP versus FTPS/SFTP, database host/port, upload limits, writable paths, backups, and PHP error-log access.

## Prepare and upload

1. Back up the current web files and export the database from phpMyAdmin. Test that the export is readable.
2. Run `composer install --no-dev --prefer-dist` and `composer dump-autoload -o` locally.
3. Upload the complete application, including `vendor/`, the root `index.php`,
   `.htaccess`, `public/assets/`, and `storage/logs/`.
4. Set deployment values in the uncommitted `.env`; do not edit PHP source or
   maintain production values in `config/config.php`.
5. Keep the shipped root `.htaccess`, which exposes the front controller and
   public assets while denying HTTP access to private application directories.
6. Set `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL` to the HTTPS origin, and
   `APP_BASE_PATH` to the installation subdirectory.
7. Make runtime files read-only where possible (typically files `0644`, directories `0755`). Only a deliberately used log/upload directory should be writable; never use `0777`.

## Database and smoke test

In phpMyAdmin, select the intended database, import migrations `001` through `010`, and confirm all ten versions. Migration `010` preserves existing allocations as unassigned effort while adding optional Work Package breakdown. Apply only absent migrations in numeric order using [DATABASE.md](DATABASE.md).

Create the first administrator with a validated `--username` through `bin/create-admin.php` from a controlled local/Docker PHP environment connected to the production database. When the hosting network makes that impossible, generate a `PASSWORD_DEFAULT` hash locally and insert only the hash through phpMyAdmin; never put a plain password in SQL. Set production session timeouts and password minimum in `config.php`.

Visit `/`, `/health`, `/login`, an unknown URL, and a known URL with the wrong method. Verify POST-only logout, admin access, non-admin 403 responses on admin routes, session expiry, the single-admin constraint, HTTPS, assets, subdirectory links, safe 404/405 behavior, and that `/csrf-test` returns 404 in production. Smoke-test people, projects, and project participants: ownership enforcement, linked/unlinked people, duplicate prevention, role/date validation, search/filter/pagination, independent state warnings, note omission, activation/deactivation, and confirmed relationship-only removal. Usernames must render without an `@` prefix. If rewrite fails, set `clean_urls=false`.

Milestone 6 restricts participant removal while person-hour allocations exist. Smoke-test planned/actual decimals, monthly boundaries, PM derivation, factor warnings, totals, note privacy, and explicit allocation removal.
Also smoke-test annual capacity, override precedence/removal, administrator-only writes, private override notes, cross-project totals, and non-blocking warnings.
Smoke-test Work Package responsibility, date limits, project-local code uniqueness, allocation breakdown and duplicates, unassigned effort, ownership, note privacy, participant-removal conflicts, and allocation-protected Work Package deletion.
Smoke-test the annual effort route: Work-Package-first hierarchy, all participants per Work Package, unified classified totals, divergent-row warning, legacy-unassigned warning, native inputs only for authorized managers, atomic validation failure, stale-write rejection, and responsive horizontal scrolling.

Milestone 10A adds no migration. Smoke-test mandatory Work Package selection, the project legacy-classification list, in-place classification, duplicate conflict, ownership rechecks, private-note handling, and conservation of overall/person/capacity totals.

Milestone 10B adds no migration and no asset build step. Deploy `public/assets/js/annual-effort-decimal.js`, `public/assets/js/annual-effort.js`, and the updated stylesheet/layout with the PHP files. Smoke-test cache refresh, dirty/reset/navigation warnings, provisional totals, keyboard focus, responsive month selection, natural WP ordering, read-only markup, and the complete no-JavaScript form.

Verify that grid saves synchronize both stored columns, divergent records remain unchanged, current-month labels follow the configured application timezone, all totals share the fixed colgroup, and WP/participant create forms receive safe project-boundary defaults.

## Rollback

For a bad release, stop or limit access if possible, preserve diagnostics, restore the prior file backup, and restore the pre-migration database export when schema/data changed. File rollback alone is unsafe after an incompatible migration. Keep credentials outside backups shared with developers and rotate any exposed secret.
## User–Person data backfill

Back up the database before the corrective deployment. After applying schema migrations and deploying the new application files, preview the one-time remediation:

```bash
php bin/backfill-user-people.php --dry-run
```

Then execute it and verify idempotency:

```bash
php bin/backfill-user-people.php
php bin/backfill-user-people.php
```

The second execution must report zero unlinked Users and zero created People. The command runs the complete write in one transaction. On failure it rolls back all People inserted by that execution; restore the database backup only if separate deployment work requires recovery.

Independent SQL verification:

```sql
SELECT COUNT(*)
FROM users u
LEFT JOIN people p ON p.user_id = u.id
WHERE p.id IS NULL;
```

The result must be zero. All human accounts, including the administrator, are included; there are currently no technical-account exclusions. Automatically created People copy first name, last name, email and active state, use position `other`, external status, `125.00` standard monthly capacity, and `NULL` affiliation, active dates and notes. Existing same-email Person candidates are reported as ambiguous and are not linked automatically.
