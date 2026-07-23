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

## Users

Migration `002_create_users.sql` adds normalized lowercase unique emails, `PASSWORD_DEFAULT` hashes, roles, active state, login time, and timestamps. Migration `005` expands the constraint to exactly `admin`, `project_manager`, `participant`, and `viewer`. Application transactions and locked rows enforce one active administrator.

```sql
SELECT version FROM schema_versions ORDER BY version;
SELECT id, email, role, is_active FROM users;
```

Never expose `password_hash`. Administrator deactivation/demotion locks relevant rows in a transaction so the database retains at least one active administrator.

Migration `003_add_usernames.sql` adds `username` as nullable, assigns every existing row `user-{id}`, then changes it to `NOT NULL` and adds `users_username_unique`. This lowercase backfill is valid and unique because the primary key is unique; it uses no triggers, routines, or generated columns.

After migration, assign the existing administrator’s final username:

```sql
UPDATE users
SET username = 'luca.tonin'
WHERE email = 'luca.tonin@unipd.it';
```

The unique index causes a visible error if the username is already used. Application code normalizes usernames to lowercase and performs prepared, exclusion-aware uniqueness checks.

## People registry

Migration `004_create_people.sql` creates `people`. `user_id` is nullable, unique when present, and references `users.id ON DELETE SET NULL`. Institutional email is nullable and unique; empty values become SQL `NULL`. Account and person names, emails, and active states are intentionally independent.

Positions use portable `VARCHAR` values: `full_professor`, `associate_professor`, `assistant_professor`, `researcher`, `postdoc`, `phd_student`, `research_fellow`, `technician`, `administrative_staff`, `external_collaborator`, and `other`. The generic `faculty` value is unsupported.

`is_internal` means membership in the managing group or institution. Association dates are general availability dates, not project dates; `active_to` cannot precede `active_from`. `is_active` controls ordinary future selection independently from the linked user. Notes are limited to 2,000 characters by application validation.

## Projects

Migration `005_add_project_manager_and_projects.sql` creates `projects`. Acronym is required and unique; internal code and grant agreement number are nullable unique identifiers. `manager_person_id` is nullable and references `people.id ON DELETE SET NULL`. Deleting or disabling an account therefore does not erase project ownership identity.

Statuses are `planned`, `active`, `completed`, `suspended`, and `cancelled`. Dates use `DATE`; an end date cannot precede a start date. Budget uses `DECIMAL(15,2)` and is present only together with a three-letter currency code. Nullable text inputs are stored as SQL `NULL`. Indexes support status, dates, manager, funding agency, and programme filters.

## Project participants

Migration `006_create_project_participants.sql` creates the many-to-many relationship. `project_id` cascades on project deletion; `person_id` restricts person deletion. A unique `(project_id, person_id)` index permits one primary role per person per project. Role, active state, and both participation dates are indexed.

`project_role` is a portable `VARCHAR`: `principal_investigator`, `coordinator`, `local_coordinator`, `work_package_leader`, `task_leader`, `researcher`, `postdoc`, `phd_student`, `research_fellow`, `technician`, `administrative_support`, `external_collaborator`, `consultant`, or `other`. Application code owns the labels and validation.

Participation dates are nullable. When present they must be ordered and fit all known project and person-association boundaries. `is_active` is stored independently from dates and from project, person, and account state. Empty notes become `NULL`.
