# Local development

Start a clean environment with:

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
```

MySQL applies migrations `001` through `004` only when creating an empty named volume. For an existing volume, import the missing migration through phpMyAdmin at <http://localhost:8081> and confirm `schema_versions`.

Create the initial admin:

```bash
read -s ADMIN_PASSWORD
export ADMIN_PASSWORD
docker compose exec -e ADMIN_PASSWORD="$ADMIN_PASSWORD" web \
  php bin/create-admin.php --username=local.admin --email=admin@example.test --first-name=Local --last-name=Admin
unset ADMIN_PASSWORD
```

Open <http://localhost:8080/login> and sign in using either email or `local.admin`. Admin user management is at `/admin/users`. Create representative `participant` and `viewer` accounts there; they must receive 403 on admin routes.

People management is at `/admin/people`. To create and deliberately link a person:

```bash
docker compose exec web php bin/create-person.php \
  --first-name=Local --last-name=Researcher --position=researcher \
  --email=researcher@example.test --username=local.admin
```

Run verification:

```bash
docker compose run --rm web composer install
docker compose run --rm web vendor/bin/phpunit
docker compose exec web sh -lc "find app bootstrap bin config public tests views -name '*.php' -exec php -l {} \\;"
docker compose config --quiet
composer validate --strict
```

Tests are isolated and use in-memory repositories unless explicitly documented as database integration tests. Do not point tests at production. Stop with `docker compose down`; use `docker compose down --volumes` only to intentionally erase local database data.
