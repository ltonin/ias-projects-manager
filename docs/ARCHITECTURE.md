# Architecture

## Overview and request lifecycle

The application is a small, custom, server-rendered PHP system rather than a generic framework. Apache exposes only `public/`; every non-file request is rewritten to `public/index.php`.

1. `public/index.php` loads `bootstrap/app.php`.
2. Bootstrap defines the project root, registers the `App\` autoloader, merges example/local/environment configuration, sets errors and timezone, starts a secure session, and adds security headers.
3. Bootstrap constructs dependencies explicitly, registers routes, and dispatches a `Request`.
4. A controller checks request concerns, calls services, and returns a `Response`.
5. Services enforce application rules; repository implementations own SQL.
6. `View` renders a content template inside a layout; the response is sent by the front controller.

Exceptions become generic 403/404/405/500 pages. Production logs details through PHP while returning no trace or sensitive data.

## Responsibilities and dependency direction

- `Auth`: session metadata, CSRF, current-user request caching, and reusable authorization guards.
- `Controllers`: request coordination only; no SQL or business rules.
- `Database`: PDO construction and later transaction support.
- `Http`: small request/response value objects.
- `Routing`: route registration and static/parameter matching.
- `Services`: use cases and business rules.
- `Repositories`: interfaces used by services and PDO implementations containing prepared queries.
- `Validation`: reusable input normalization and validation rules.
- `Models`: domain data/DTOs independent from HTTP and PDO.
- `Support`: configuration, URLs, flash messages, and views.
- `views`: escaped presentation only.

Dependencies point inward: HTTP/controllers depend on services; services may depend on repositories; repositories depend on PDO and models. Models do not depend on HTTP, controllers, or views. Constructor injection is preferred. No static service locator or global PDO is used.

Empty application directories are intentional architectural boundaries for the next phases, not a framework abstraction.

## Routing and URLs

Routes are defined once regardless of transport form. With `clean_urls=true`, `.htaccess` maps `/health` to the front controller. With rewriting unavailable, `index.php?route=health` produces the same request path. `base_path` is removed from incoming paths; `UrlGenerator` adds it to links, assets, redirects, and fallback URLs.

Authentication routes are `GET|POST /login` and POST-only `/logout`. Admin-only user management lives under `/admin/users`; development-only `POST /csrf-test` remains. Static routes and `{parameter}` segments are supported. A matched path with another method yields 405; unknown paths yield 404.

## Configuration, database, and views

`config.example.php` is the safe baseline. Ignored `config.php` recursively overrides it; defined Docker environment values override both. A production PHP config file therefore works without server commands. Filesystem roots use `dirname()` and URLs use configuration.

`ConnectionFactory` creates explicit PDO dependencies with exception mode, associative fetches, native prepared statements, and `utf8mb4`. It replaces driver exceptions with a credential-free message. Future multi-step writes must use transactions in services/repositories.

Login identifiers are normalized once: values containing `@` use normalized email lookup, while other values use canonical lowercase username lookup. Repository methods issue direct prepared queries, so no user collection is filtered in PHP.

`AuthSession` stores only user ID, authentication time, and last activity. `CurrentUser` and `CurrentPerson` cache their lookups once per request. `Authorization` provides authenticated/admin guards; `ProjectPolicy` centralizes project ownership and private-note decisions. `AuthenticationService` owns password verification, rehashing, and login state. `UserService` owns normalization and account use cases. Single-admin checks and changes use locked rows in a PDO transaction.

Templates receive explicit data, contain no application logic, and escape dynamic text with `View::escape`. The renderer supports layouts, titles, errors, and session flash messages. Bootstrap 5.3.8 is checked into `public/assets/vendor`.

## Users, people, and projects

`User` represents credentials, username, application role, and account status. `Person` represents a potential research participant and contains no authentication or authorization data. `people.user_id` is optional and unique with `ON DELETE SET NULL`.

```text
users
  0..1
   |
  0..1
people
```

`PersonService` normalizes explicitly accepted fields and owns validation/link rules. `PdoPersonRepository` owns prepared persistence, escaped-wildcard search, filters, deterministic ordering, and pagination.

```text
users 0..1 --- 0..1 people
people 0..1 --- * projects
```

`projects.manager_person_id` is nullable and references the responsible person. `ProjectService` normalizes input and prevents project managers from assigning or changing ownership. `PdoProjectRepository` rechecks ownership in write SQL to close the gap between authorization and update, and owns combined search/filter/pagination. List objects and unauthorized detail models omit notes.

Future participation remains a separate many-to-many association and is intentionally not implemented:

```text
people 1 --- * project_participants * --- 1 projects
```

## Why no full framework

The hosting target cannot run Composer or commands and must be deployable through FTP. The small scope does not justify a framework runtime, ORM, template engine, or build pipeline. The custom pieces remain deliberately narrow; contributors must extend domain behavior rather than grow a general framework.

## Contributor rules

- Keep controllers thin, SQL in repositories, and rules in services.
- Use strict types, `App\` namespaces, typed APIs, final classes by default, prepared statements, and immutable dates.
- Validate at the application boundary and escape at output.
- Add POST routes with CSRF validation and authorization where applicable.
- Do not hard-code paths/hosts or leak exception detail.
- Add an independent, documented SQL migration for every schema change.
- Do not add production dependencies or abstractions without a concrete repeated need.
