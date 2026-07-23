# Research Project Manager

A lightweight, server-rendered PHP application foundation for a university research group. It is intended to manage projects, people, participation, and monthly person-month allocations in later phases.

**Current status:** technical foundation only. The application is not feature-complete and contains no user, project, participant, or allocation CRUD.

## Requirements and local setup

- Docker Engine with Docker Compose v2
- Git
- Composer only when running PHPUnit outside Docker (development only)

```bash
cp .env.example .env
docker compose up --build
```

The example values are local-only; choose different passwords if the database port is accessible to others. Docker initializes a new database volume with migration `001`.

- Application: <http://localhost:8080>
- Health: <http://localhost:8080/health>
- phpMyAdmin: <http://localhost:8081>

Stop with `docker compose down`. Add `--volumes` only when intentionally discarding the local database.

## Configuration

Defaults live in `config/config.example.php`. Docker overrides them with environment variables. For non-Docker/shared hosting, copy that file to `config/config.php`, set production values, and keep it uncommitted. Set `base_url` to the scheme and host and `base_path` to the installation path (for example `/research-manager`).

Set `clean_urls` to `false` when rewriting is unavailable. The same internal routes then use forms such as `index.php?route=health`.

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
