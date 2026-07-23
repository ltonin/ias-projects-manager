# Research Project Manager

A lightweight, server-rendered PHP application foundation for a university research group. It is intended to manage projects, people, participation, and monthly person-month allocations in later phases.

**Current status:** milestone 5 adds project participants, project-specific roles and dates, ownership-based management, filtering, pagination, and private participation notes. Person-month allocations, reports, invitations, email, audit logging, and password-reset email are not implemented.

## Requirements and local setup

- Docker Engine with Docker Compose v2
- Git
- Composer only when running PHPUnit outside Docker (development only)

```bash
cp .env.example .env
docker compose up --build
```

The example values are local-only; choose different passwords if the database port is accessible to others. Docker initializes a new database volume with migrations `001`–`006`. Import each missing migration once through phpMyAdmin for an existing database.

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

The administrator may create, edit, reassign, leave unassigned, and change the status of every project. A `project_manager` must first be linked to a person record and may create and manage only projects owned by that person. Submitted ownership fields cannot be used to take over or reassign a project. Participants and viewers have read-only access. Project notes are shown only to the administrator and the owning project manager and never appear in lists.

Ownership deliberately references `people.id`, not `users.id`, so research identity remains stable if account access changes. Budget and currency must be supplied together; budgets use `DECIMAL(15,2)` and currencies use three-letter codes.

Apply migration `005_add_project_manager_and_projects.sql` to an existing database after taking a backup. Project creation uses the authenticated web UI; there is intentionally no unattended project-creation CLI because ownership and private-note authorization require an authenticated actor.

## Project participants

Participant records connect `projects` to `people`; they never reference users. Each person has at most one row per project and one primary project role. The supported roles range from Principal Investigator and Project Coordinator through research, technical, administrative, external-collaborator, consultant, and other roles.

Project ownership (`projects.manager_person_id`) and participation (`project_participants`) are independent. A responsible manager is not added automatically, ownership changes do not modify participants, and removing a participant does not change ownership, the person, or a linked account.

All authenticated users may browse participant lists and details through a project. The administrator manages participants everywhere; an owning project manager manages participants only on owned projects. Participant notes are visible only to those managers and are absent from list data and unauthorized detail models.

Participation dates must be internally ordered and must fit known project and person-association boundaries. Participation, person, linked-account, and project active states remain independent and are shown separately. Lists support person/username/role search, participation state, role, internal/external, person-state filters, and server-side pagination.

Apply `database/migrations/006_create_project_participants.sql` after migration `005`.

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
