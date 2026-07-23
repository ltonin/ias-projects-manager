# Research Project Manager

A lightweight, server-rendered PHP application for managing research projects, people, participation, and monthly planned and actual person-hours.

**Current status:** Milestone 10B hardens the Work-Package-first annual grid with decimal-safe provisional totals, dirty tracking, navigation and filtering tools, mobile month focus, accessible cell states, and progressive enhancement.

## Requirements and local setup

- Docker Engine with Docker Compose v2
- Git
- Composer only when running PHPUnit outside Docker (development only)

```bash
cp .env.example .env
docker compose up --build
```

The example values are local-only; choose different passwords if the database port is accessible to others. Docker initializes a new database volume with migrations `001`–`010`. Import each missing migration once through phpMyAdmin for an existing database.

- Application: <http://localhost:8080>
- Health: <http://localhost:8080/health>
- phpMyAdmin: <http://localhost:8081>

Stop with `docker compose down`. Add `--volumes` only when intentionally discarding the local database.

## First administrator and authentication

After migration `002`, create the first administrator using the non-web CLI script:

```bash
read -s ADMIN_PASSWORD
export ADMIN_PASSWORD
docker compose exec -e ADMIN_PASSWORD="$ADMIN_PASSWORD" web \
  php bin/create-admin.php --username=ada.lovelace --email=admin@example.edu --first-name=Ada --last-name=Lovelace
unset ADMIN_PASSWORD
```

Omit the name/email options to be prompted. The password is accepted only through `ADMIN_PASSWORD`, so it is not echoed or placed in command arguments. Prefer a temporary environment value and clear it afterward.

Login at `/login` with either email or username; logout is POST-only. Roles are exactly `admin`, `project_manager`, `participant`, and `viewer`. The application permits exactly one active administrator: ordinary user management cannot create or promote another administrator, and the existing administrator cannot be demoted or deactivated.

Passwords use PHP `PASSWORD_DEFAULT`, accept passphrases without composition rules, and default to 12–4096 characters. Successful login transparently rehashes outdated hashes. Password-reset email is not implemented.

Usernames are trimmed and stored lowercase. They contain 3–50 characters from `a-z`, `0-9`, `.`, `_`, and `-`, and must start and end with a letter or number. Reserved names are `admin`, `administrator`, `root`, `system`, `support`, `login`, `logout`, `health`, `api`, and `assets`.

Migration `003` assigns existing rows the deterministic unique username `user-{id}`. Set the known administrator username afterward:

```bash
docker compose exec -T database sh -lc \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < database/migrations/003_add_usernames.sql

docker compose exec -T database sh -lc \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" \
  -e "UPDATE users SET username = '\''luca.tonin'\'' WHERE email = '\''luca.tonin@unipd.it'\'';"'
```

The database unique constraint makes this update fail visibly if `luca.tonin` is already used.

## People registry

Users are application accounts; people are individuals who may participate in future research projects. A person may have no account, and the optional relationship is one-to-one. Account and person names, emails, and active states are independent and never synchronized automatically.

```text
users
  0..1
   |
  0..1
people
```

Admins manage people at `/admin/people`, with search, active/internal/position/link filters, and server-side pagination. Supported positions are Full Professor, Associate Professor, Assistant Professor, Researcher, Postdoctoral Researcher, PhD Student, Research Fellow, Technician, Administrative Staff, External Collaborator, and Other. The obsolete generic `faculty` value is not supported.

`is_internal` means membership in the managing group or institution rather than an external collaborator. Association dates describe the general relationship period; `is_active` controls normal future selection. Both are independent from a linked account’s active state.

```bash
docker compose exec web php bin/create-person.php \
  --first-name=Luca --last-name=Tonin --position=researcher \
  --email=luca.tonin@unipd.it --affiliation="University of Padua" \
  --username=luca.tonin
```

Usernames are displayed without an `@` prefix. Email addresses retain their normal `@`.

Apply the people migration to an existing database:

```bash
docker compose exec -T database sh -lc \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < database/migrations/004_create_people.sql
```

## Project registry

All authenticated users may browse `/projects` and open project details. Search covers project identifiers, funding fields, coordinator, and responsible-person details; status, responsible person, funding agency, and programme filters combine with server-side pagination.

The administrator may create, edit, reassign, leave without an owner, and change the status of every project. A `project_manager` must first be linked to a person record and may create and manage only projects owned by that person. Submitted ownership fields cannot be used to take over or reassign a project. Participants and viewers have read-only access. Project notes are shown only to the administrator and the owning project manager and never appear in lists.

Ownership deliberately references `people.id`, not `users.id`, so research identity remains stable if account access changes. Budget and currency must be supplied together; budgets use `DECIMAL(15,2)` and currencies use three-letter codes.

Apply migration `005_add_project_manager_and_projects.sql` to an existing database after taking a backup. Project creation uses the authenticated web UI; there is intentionally no unattended project-creation CLI because ownership and private-note authorization require an authenticated actor.

## Project participants

Participant records connect `projects` to `people`; they never reference users. Each person has at most one row per project and one primary project role. The supported roles range from Principal Investigator and Project Coordinator through research, technical, administrative, external-collaborator, consultant, and other roles.

Project ownership (`projects.manager_person_id`) and participation (`project_participants`) are independent. A responsible manager is not added automatically, ownership changes do not modify participants, and removing a participant does not change ownership, the person, or a linked account.

All authenticated users may browse participant lists and details through a project. The administrator manages participants everywhere; an owning project manager manages participants only on owned projects. Participant notes are visible only to those managers and are absent from list data and unauthorized detail models.

Participation dates must be internally ordered and must fit known project and person-association boundaries. Participation, person, linked-account, and project active states remain independent and are shown separately. Lists support person/username/role search, participation state, role, internal/external, person-state filters, and server-side pagination.

Apply `database/migrations/006_create_project_participants.sql` after migration `005`.

## Monthly person-hour allocations

Each project has an `hours_per_pm` conversion factor, defaulting to `125.00`. Monthly rows store planned hours, actual hours, or both against a project participant and a mandatory same-project Work Package. New null/unassigned rows are rejected by services and repositories. Existing `NULL` rows are transitional legacy data only; no fake Work Package is created.

Person-Month values are never persisted. They are derived as `hours / project.hours_per_pm` using fixed-precision integer arithmetic. Changing the factor changes displayed PM equivalents while leaving stored hours unchanged; factor history is not retained.

Allocation months must overlap all known project, participation, and person-association periods and, when assigned, the Work Package period. Active flags do not prevent allocations. Values above one PM and cross-project totals above either the conversion factor or effective person capacity are accepted; capacity warnings are informational.

All authenticated users may read allocations and totals. The administrator and owning project manager may manage them and see private notes. Participants with historical allocations cannot be physically removed until each allocation is explicitly removed.

Apply `database/migrations/007_add_person_hour_allocations.sql` after migration `006`.

## Person capacity

Every person has a standard monthly capacity, defaulting to `125.00` hours, plus optional calendar-month overrides. An override replaces the standard only for its month. Capacity is person-level availability across all projects and is independent from each project’s hours-per-PM conversion.

Annual capacity pages at `/people/{id}/capacity` show all 12 months, effective source, planned/actual cross-project hours, remaining capacity, and independent status. Negative remaining values produce accessible warnings but never block allocation or capacity writes. Zero capacity is valid and does not change person active state.

All authenticated users may read numeric capacity information. Only administrators may change standards or manage overrides, and override notes are physically absent from non-administrator models and pages. Apply `database/migrations/008_add_person_capacity.sql` after migration `007`.

## Work Packages

A Work Package belongs to one project and may identify one responsible project participant. Responsibility is optional and is distinct from project ownership: it neither changes `projects.manager_person_id` nor creates a participation relationship. Active and inactive, linked and unlinked, internal and external project participants may be responsible; independent inactive states produce warnings.

Work Package dates are optional, internally ordered, and must remain within every known project boundary. Project edits that would invalidate existing Work Packages are rejected. Every authenticated role can read Work Packages; administrators and the owning project manager can manage them and view private notes. A responsible participant cannot be physically removed until responsibility is reassigned or cleared, although deactivation remains available.

The registry is available under `/projects/{projectId}/work-packages`, with nested create, detail, edit, activate/deactivate, and removal routes. Work Package details include effort totals and participant/month summaries. A Work Package referenced by allocations cannot be removed until those rows are removed or reassigned.

Apply `database/migrations/009_create_work_packages.sql` after migration `008`, then `database/migrations/010_add_work_package_allocations.sql`.

## Annual effort grid

`/projects/{projectId}/effort` is the primary person-hours entry interface. It groups by real Work Packages first and renders every project participant beneath every Work Package that overlaps the selected year or contains historical allocations. The current UI exposes one Person-hours value; each grid write atomically copies it to both retained database columns, `planned_hours` and `actual_hours`. Those columns remain part of the domain and are never summed together.

The matrix never renders an unassigned or synthetic Work Package section. Legacy `NULL` Work Package allocations appear only in an informational summary and remain editable through detailed allocation workflows. Grid totals are classified-only. Empty cells create no rows, explicit zero is preserved, date-invalid cells are unavailable, and existing inconsistent rows remain linked to their detail page.

The detailed form and annual grid require a real Work Package. `/projects/{projectId}/allocations/unassigned` lists legacy rows and links authorized administrators or the current owning manager to an in-place classification form. Reclassification preserves ID, period, hours, notes, and participant totals; a duplicate participant/WP/month target is rejected without merging. A future migration may make the column non-null only after every legacy row is classified.

Bulk saves are one transaction and preserve private notes. A deterministic allocation snapshot rejects stale grids. Records whose planned and actual values differ are read-only, linked to allocation details, and excluded from unified totals so no historical value is selected or overwritten silently. Administrators and owning project managers receive native inputs; other authenticated roles receive no writable payload metadata.

JavaScript compares normalized decimal strings, so empty and explicit zero remain distinct while `10` and `10.00` are equivalent. Dirty cells update provisional unified row, Work Package, project-hour, and project-PM totals using integer hundredths and server-equivalent half-up PM rounding. The save bar reports changed cells, warns before navigation, and can restore server-rendered values; it never autosaves or submits client totals.

Enter/Shift+Enter moves across editable months. Arrow keys move at text-cursor boundaries, while Tab remains native. Narrow screens can focus one month with previous/next controls without losing dirty inputs. Work Packages support expand/collapse-all, jump navigation, natural code order (`WP1`, `WP2`, `WP10`), participant/WP filters, and non-color-only dirty/warning states. With JavaScript disabled, all twelve months, native inputs, server totals, CSRF, concurrency, and atomic submission remain available.

Every annual table uses the same responsive participant, twelve-month, and annual colgroup. At desktop widths the table consumes exactly its grid wrapper: the hierarchy receives 240px at wide desktop or 208px at compact desktop, the annual column receives 88px or 80px, and twelve months share the remainder. Common bounded cell containers keep editable, unavailable, read-only, zero, inconsistent, and divergent cells geometrically identical. Narrow screens retain grid-local scrolling/month focus without introducing document-level horizontal overflow. When the selected year is the current year, the current month is labelled from the configured server timezone (`Europe/Rome` by default), not the browser clock. New WP forms default to project dates; new participant forms default to the project/person interval intersection. No migration is required.

## Configuration

Defaults live in `config/config.example.php`. Docker overrides them with environment variables. For non-Docker/shared hosting, copy that file to `config/config.php`, set production values, and keep it uncommitted. Set `base_url` to the scheme and host and `base_path` to the installation path (for example `/research-manager`).

Set `clean_urls` to `false` when rewriting is unavailable. The same internal routes then use forms such as `index.php?route=health`.

Session settings are `SESSION_IDLE_TIMEOUT` (default 1800 seconds), `SESSION_ABSOLUTE_TIMEOUT` (28800), and `PASSWORD_MIN_LENGTH` (12). The absolute timeout must not be shorter than the idle timeout; the password minimum must be between 8 and 4096. Shared hosting can set equivalent values in `config/config.php`.

## Tests

PHPUnit is strictly a development dependency:

```bash
docker compose run --rm web composer install
docker compose run --rm web vendor/bin/phpunit
```

Alternatively, on a workstation with Composer and PHP's DOM/XML and mbstring extensions: `composer install && vendor/bin/phpunit`.

Production does not require Composer or `vendor/`. The tests do not connect to or mutate MySQL. Quick source checks can be run with:

```bash
find app bootstrap config public tests views -name '*.php' -exec php -l {} \;
```

## Directory overview

- `app/`: application classes, split by responsibility
- `bootstrap/`: autoloading and application composition
- `config/`: safe example and ignored local configuration
- `database/`: schema entry point, manual migrations, and future seeds
- `docker/`, `compose.yaml`, `Dockerfile`: development environment
- `docs/`: architecture, operations, security, and contribution guidance
- `public/`: the only web-accessible directory and front controller
- `storage/`: ignored runtime logs
- `tests/`: isolated unit tests
- `views/`: layouts and presentation templates

## Production constraints

Deployment targets basic Aruba shared hosting: FTP upload, phpMyAdmin migrations, no SSH, no server-side Composer, and possible subdirectory installation. See [docs/DEPLOYMENT_ARUBA.md](docs/DEPLOYMENT_ARUBA.md). Upload application runtime files but omit `.git/`, `.env`, `tests/`, `vendor/`, Docker files, PHPUnit files, and local logs.

Detailed design and future phases are in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and [docs/ROADMAP.md](docs/ROADMAP.md).
# Milestone 11 workflow

The authenticated landing page is a read-only annual overview of every project visible to the current user, organized as Project → Work Package → participant. A persistent desktop sidebar provides direct project access and becomes an accessible Bootstrap off-canvas menu on small screens. Project pages open read-only; administrators and the current owning project manager must choose **Edit hours** before any hour form, CSRF token, or concurrency token is rendered.

The overview loads its selected-year hierarchy in a bounded batch (at most 200 accessible projects) rather than invoking the project annual-grid query sequence once per project. Equal planned/actual pairs contribute once; divergent and legacy-unassigned records are counted as warnings and excluded from classified totals.

New projects use a four-step server-side workflow: details, Work Packages, participants, and review. The incomplete state remains in the authenticated session and is never visible as a production project. Final confirmation revalidates referenced people and creates the project, WPs, and participants in one database transaction. Person-hours are deliberately absent from every creation step.

## Milestone 11.1 layout regression

The authenticated browser regression uses the real Bootstrap application shell and checks `/`, `/projects/{id}`, and `/projects/{id}/effort/edit` at 1920×1080, 1440×900, 1366×768, and 390×844 with device scale factor 1 (100% zoom). It requires a disposable administrator and representative project fixture supplied by the developer:

```bash
M111_BROWSER_USER=... M111_BROWSER_PASSWORD=... \
python3 tests/browser/milestone11_1_layout.py --project PROJECT_ID --assert-fit
```

Supported representative desktop layouts require neither document-level nor grid-level horizontal scrolling. At narrow/mobile widths only the annual-grid wrapper may scroll. Sidebar colors are centralized in `app.css`; the dark `#15324b` background is explicitly restored over Bootstrap’s responsive off-canvas transparency rule.
Authenticated desktop pages use an expanded 248 px navigation sidebar by default. Its accessible collapse button reduces it to a persistent 56 px icon rail and stores only `expanded` or `collapsed` under `iaspm.sidebar`. In collapsed mode, the Projects and Administration icons temporarily restore the full navigation so project names and role-gated administrative links remain usable. Without JavaScript and on mobile, full-text navigation remains available.
Authorized administrators and project managers can open `/capacity?year=YYYY` for a batched, read-only all-People overview. Administrator scope is the full registry; project-manager scope is limited to the manager and People participating in projects they manage. Other roles retain their linked Person's capacity page. Annual Person panels are expanded without JavaScript and progressively enhanced with accessible per-Person and page-level disclosure controls.
