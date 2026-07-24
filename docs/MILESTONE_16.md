# Milestone 16 — Operations Dashboard and Database Backup

## Deployment metadata

Runtime metadata is read from `storage/deployment.json`. It is deliberately not
derived from file modification times. Release automation should run:

```text
php bin/write-deployment-metadata.php \
  --version=16.0.0 \
  --build-version=release-2026.07.24 \
  --commit=<full-git-commit> \
  --build-date=<ISO-8601-build-time>
```

The command records the deployment time when it runs. The generated file is
ignored by Git so one release cannot accidentally reuse another release's
deployment date. `storage/deployment.json.example` documents the format.
Unavailable metadata is shown honestly as unavailable; no timestamp is guessed.

## Operations dashboard

`Administration → System` is divided into Application, Runtime, Database,
Diagnostics, and Maintenance. It reports deployment metadata, PHP/web/database
versions, timezone and server time, deployment-based application uptime, paths
and writability, database size and record counts.

Diagnostics cover unlinked users, duplicate normalized usernames/emails, orphan
allocations, projects without Work Packages or Participants, invalid annual
capacity, pending migrations, and applied migration identifiers that do not
exist in the release. Warning styling is used only for a non-zero issue count.

## Backup architecture and SQL format

`POST /admin/system/backup` creates an attachment named
`iaslab-projects-YYYY-MM-DD-HHMM.sql`. `DatabaseBackupService` uses PDO and MySQL
metadata only; it does not require shell access or `mysqldump`.

The stream contains:

- UTF-8 initialization and foreign-key-safe import guards;
- `SHOW CREATE TABLE` output, preserving columns, AUTO_INCREMENT, indexes,
  checks, and foreign keys;
- data as quoted multi-row `INSERT` statements, emitted in batches of 100 rows;
- `SHOW CREATE VIEW` output for views, when present.

No complete dump string or permanent backup file is created by the HTTP flow.
The controller writes each generated chunk to the response immediately.

MySQL users need `SELECT`, `SHOW VIEW`, and sufficient metadata visibility for
all exported objects. Stored routines, triggers, scheduled events, and database
users/grants are outside this application's schema backup. View definitions are
exported exactly as MySQL returns them, including a definer if one exists.

## Security

The dashboard and backup action both require the administrator role. Backup is
POST-only, validates the session CSRF token, sends `no-store`, and uses a fixed
server-generated filename. Requested and completed downloads are written to the
configured application error log with user ID and timestamp; credentials and
SQL data are not logged.

Restore, log/cache clearing, and log download appear only as disabled future
hooks.

## Verification

`Milestone16OperationsTest` covers metadata parsing, streamed responses, a real
database schema/data export, SQL structural properties, route authorization,
CSRF wiring, and the maintenance UI. The complete PHPUnit suite is executed in
the application container. `tests/browser/system_backup.py` signs in as an
administrator, checks all dashboard sections, downloads the backup through the
actual HTTP endpoint, and validates its headers and SQL framing.

Final PHPUnit result: 158 tests, 731 assertions, 0 failures, with 6 pre-existing
PHPUnit warnings.

## Files introduced or modified

- `app/Controllers/AdminSystemController.php`
- `app/Http/Response.php`
- `app/Services/DatabaseBackupService.php`
- `app/Services/DeploymentMetadataService.php`
- `bin/write-deployment-metadata.php`
- `bootstrap/app.php`
- `views/admin/system.php`
- `storage/deployment.json.example`
- `.gitignore`
- `tests/Unit/Milestone16OperationsTest.php`
- `tests/Unit/StagingReadinessTest.php`
- `tests/browser/system_backup.py`
- `docs/MILESTONE_16.md`
