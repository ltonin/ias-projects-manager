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

## Person-hour allocations

Migration `007_add_person_hour_allocations.sql` adds required `projects.hours_per_pm DECIMAL(8,2) DEFAULT 125.00`, backfilling existing projects through the column default. It creates `person_hour_allocations` with a restrictive participant foreign key, unique `(project_participant_id, year, month)`, period indexes, years 2000–2100, months 1–12, and a check requiring planned or actual hours.

Migration `010` replaces that original participant/month unique index with the normalized Work Package-aware uniqueness described below.

Planned and actual values are nullable `DECIMAL(8,2)`. `NULL` and explicit `0.00` are distinct. Person-Month values are not columns; they are calculated from stored hours and the project’s current factor. Factor changes reinterpret historical PM display without altering hours, and no factor history exists.

## Person capacity

Migration `008_add_person_capacity.sql` adds `people.default_monthly_capacity_hours DECIMAL(8,2) NOT NULL DEFAULT 125.00` and backfills existing people. `person_month_capacity_overrides` contains unique `(person_id, year, month)` values, `DECIMAL(8,2)` available hours, private notes, timestamps, period indexes, and `ON DELETE CASCADE` only to its owning person.

Effective capacity is an application-derived override-or-standard value. Allocation totals remain SQL sums through people → participants → allocations. Capacity and project `hours_per_pm` are intentionally independent.

## Work Packages

Migration `009_create_work_packages.sql` creates `work_packages` with a cascading project foreign key, nullable restrictive responsible-participant foreign key, case-insensitive unique `(project_id, code)`, state/date/responsibility indexes, optional dates and text, and timestamps. Codes use the database’s `utf8mb4_unicode_ci` collation, so uniqueness is case-insensitive within each project.

Registry ordering is deterministic lexical code order followed by ID; codes such as `WP10` therefore sort before `WP2`.

The ordinary responsible-participant foreign key preserves referenced participants but cannot by itself ensure matching projects. Services validate the relationship and PDO create/update statements conditionally require the participant and Work Package to belong to the same project. Work Package dates must be internally ordered and remain within known project dates.

There is currently no project-deletion application route. In MySQL, directly deleting a project that still has a Work Package responsibility can be rejected while the required restrictive participant foreign key is evaluated, despite the Work Package project foreign key being cascading. Any future project-removal workflow must remove its Work Packages first in a transaction; Milestone 8A does not weaken participant protection to support a feature that does not yet exist.

## Work Package allocation breakdown

Migration `010_add_work_package_allocations.sql` adds nullable `person_hour_allocations.work_package_id`, its restrictive foreign key and index, plus a required `work_package_key`. The key is `0` for unassigned effort and otherwise equals `work_package_id`, enforced by a check constraint. Unique `(project_participant_id, work_package_key, year, month)` therefore guarantees both one row per participant/WP/month and at most one unassigned participant/month row without relying on MySQL’s nullable-unique behavior or a fake Work Package.

Existing rows receive `work_package_id = NULL` and `work_package_key = 0`; their hours, dates, notes, IDs, and timestamps are not rewritten. Conditional PDO writes also require a selected Work Package to belong to the participant’s project.

Milestone 10A adds no migration. Application create and assigned-update writes require `work_package_id > 0`; only the dedicated legacy reclassification update may change a normalized `(NULL, 0)` row to `(N, N)`. It preserves every other column and rejects duplicate targets through the existing unique constraint. `work_package_id` can become `NOT NULL` in a future migration only after the legacy classification count reaches zero.

Milestone 10B adds no schema changes. Work Packages use application-level natural code ordering with ID fallback, consistently producing sequences such as `WP1`, `WP2`, `WP10` in registries, options, and the annual grid. Duplicate codes remain prohibited within a project; no persisted sort identity is introduced.

For the future annual grid, the recommended bulk allocation payload is a flat list keyed by `(project_participant_id, work_package_id|null, year, month)` with planned/actual decimals. Load one project’s participants, Work Packages, allocation rows for months 1–12, participant capacities, and cross-project person/month totals as separate bounded datasets, then build matrix cells in application memory.

Milestone 9 uses that bulk shape without a schema change. Grid writes require positive real Work Package IDs and operate on the existing normalized allocation identity. Unassigned rows remain unchanged and outside classified grid totals.

The refined Milestone 10B UI retains both decimal columns. Unified grid writes set both to the submitted value in one transaction: empty maps to both `NULL`, explicit zero to both `0.00`, and a non-zero value to the same canonical decimal. Equal rows contribute their common value once. Rows where the columns differ are excluded from unified totals and cannot be written through the grid. No migration rewrites existing rows.
