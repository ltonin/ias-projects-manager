# Database

MySQL 8 is used in development; production SQL must stay compatible with the exact Aruba MySQL/MariaDB version. Use lowercase plural table names and `snake_case` columns. Primary keys should be unsigned integer/bigint identifiers unless a documented reason favors another type. Use `utf8mb4` consistently and explicit InnoDB foreign keys with intentional delete/update actions.

Dates use `DATE`, instants use UTC `DATETIME`/`TIMESTAMP` with conversion at the boundary, and money-like/person-month values use an explicitly sized `DECIMAL`—never floating point. The exact person-month precision will be chosen with domain requirements.

## Migrations through phpMyAdmin

Migrations are ordered, immutable files such as `002_add_users.sql`. Each file must be independently executable, create one coherent change, and insert its version into `schema_versions` only after successful work. Check the current version first:

```sql
SELECT version, description, applied_at
FROM schema_versions
ORDER BY version;
```

Before applying a file, export a complete structure-and-data backup in phpMyAdmin and verify that its version is absent. Select the correct database, import the next SQL file once, then re-run the query and smoke-test the app. Never edit an already deployed migration; add a new corrective migration.

MySQL DDL may auto-commit, so rollback is not guaranteed. Every risky migration needs a documented restore plan. If failure occurs, stop writes, preserve logs/errors, and restore the verified pre-migration export or apply a separately reviewed forward fix.

Use transactions for multi-step application writes. Keep transactions short; begin/commit in the service/use-case boundary, roll back on every exception, and never perform user interaction or external I/O while a transaction is open.
