# Architecture

## Overview and request lifecycle

The application is a small, custom, server-rendered PHP system rather than a generic framework. Apache exposes only `public/`; every non-file request is rewritten to `public/index.php`.

1. `public/index.php` loads `bootstrap/app.php`.
2. Bootstrap defines the project root, registers the `App\` autoloader, merges example/local/environment configuration, sets errors and timezone, starts a secure session, and adds security headers.
3. Bootstrap constructs dependencies explicitly, registers routes, and dispatches a `Request`.
4. A controller checks request concerns, calls services, and returns a `Response`.
5. Services enforce application rules; repository implementations own SQL.
6. `View` renders a content template inside a layout; the response is sent by the front controller.

Exceptions become generic 403/404/405/409/500 pages. Production logs details through PHP while returning no trace or sensitive data.

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

Administrative User creation is one atomic use case. The administrator may explicitly select an unlinked Person; otherwise the application creates a Person, inserts the User, and links both records in one database transaction. A failure at any stage rolls back both records. Automatically created People copy the User's name and email once, use `other` as position, external status, no affiliation or active dates, and the standard 125-hour monthly capacity. Subsequent User edits never synchronize Person data.

`people.user_id` remains protected by its unique key and foreign key, so one User cannot be linked to multiple People and links cannot dangle. The existing `ON DELETE SET NULL` behavior is retained for safe account deletion; consequently the database alone cannot guarantee that every User always has a Person. Application creation and the explicit `backfill-user-people.php` deployment command enforce the operational invariant. There are no intentionally exempt account categories.

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

Participation is a separate many-to-many association with project-specific metadata:

```text
people 1 --- * project_participants * --- 1 projects
```

`ProjectParticipantService` validates project/person boundaries and use cases. `PdoProjectParticipantRepository` owns joined search, filters, pagination, uniqueness-race translation, and write-time ownership conditions. Participant list objects and unauthorized detail models omit notes. Ownership never creates, changes, or removes participant rows.

Adding an existing active Person creates only a project-level `project_participants` relationship. It does not create Work Package membership or person-hour allocations: the annual grid derives its Work Package × participant empty cells in memory, so the participant is immediately visible with empty hours. The repository locks and rechecks the project, manager ownership, Person eligibility, and duplicate invariant in one transaction; the database unique key on `(project_id, person_id)` remains the final race-safe guard.

Existing-project maintenance is organized under `/projects/{id}/configure`. Its server-rendered navigation connects project metadata, the Work Package registry, and the participant registry without duplicating their controllers or domain services. Project details post through `ProjectService`; Work Packages use `WorkPackageService`; participants use `ProjectParticipantService`. The owning project manager or administrator is re-authorized by `ProjectPolicy` for every write, and child records are checked against the route project before editing. Configuration return context carries the selected year but never changes effort data.

Monthly effort uses the participant relationship:

```text
project_participants 1 --- * person_hour_allocations
```

Person-hours are the persisted source of truth. `PersonMonthConverter` derives three-decimal PM equivalents from the current project `hours_per_pm` using integer minor units, never binary floating point. Allocation repositories own period filters and SQL aggregates; allocation writes repeat project ownership in SQL. Participant removal consults allocation existence and preserves historical relationships.

`people.default_monthly_capacity_hours` and sparse `person_month_capacity_overrides` provide person-level availability. `PersonCapacityService` resolves override precedence and builds annual summaries from a bounded override query plus one SQL allocation aggregation. `DecimalHours` performs subtraction and comparison in integer hundredths. Capacity warnings are informational and do not participate in allocation validation.

The global capacity overview uses three bounded queries: authorized People, all selected-year overrides for their IDs, and all selected-year allocation totals for their IDs. Administrators receive the complete Person registry. Project managers receive themselves and People participating in projects they manage. Other roles retain only their own single-Person capacity page.

The global annual overview first selects the authorization-and-year intersection in SQL. Project dates use an inclusive end date and a half-open calendar boundary: `(start_date IS NULL OR start_date < next_year_01_01) AND (end_date IS NULL OR end_date >= year_01_01)`. A missing start means open toward the past and a missing end means open toward the future. Status does not remove historical, suspended, completed, or cancelled projects when their dates overlap.

Future allocation classification may optionally connect a person-hour allocation to a Work Package. Its model and integrity rules will be defined before a spreadsheet-like annual effort grid; no speculative allocation column is added in Milestone 8A.

Work Packages now form `projects 1 --- * work_packages`, with optional responsibility represented by `project_participants 1 --- 0..* work_packages`. `WorkPackageService` owns normalization, date and participant validation, current-ownership checks, and removal behavior. Conditional repository writes repeat both ownership and same-project responsibility constraints. Work Package list models always omit notes; unauthorized detail models are copied without notes.

Milestone 8B connects each allocation optionally to a Work Package:

```text
person_hour_allocations * --- 0..1 work_packages
```

`NULL` now represents transitional legacy-unassigned effort only. Classified create/update paths require a positive same-project Work Package. A separate repository operation conditionally updates only normalized legacy rows in place, derives `work_package_key`, repeats hierarchy/ownership checks, and lets the unique constraint reject duplicate targets. Project, participant, person, and capacity totals continue to sum all rows; classified WP/grid totals exclude legacy rows.

Milestone 9 assembles an `AnnualEffortPage` as Work Packages → every project participant → 12 month cells. `PdoAnnualEffortRepository` loads classified rows, unassigned summary, and bulk capacity data with a fixed number of queries; empty Cartesian cells are application objects only. It never inserts placeholder allocations.

Bulk writes validate server-derived participant and Work Package allow-lists, then lock the project and classified year rows inside one transaction. A SHA-256 snapshot over allocation identity, timestamps, both stored values, and Work Package identity provides optimistic concurrency. The unified grid value is copied atomically to both `planned_hours` and `actual_hours`; note-bearing empty rows are retained. Divergent rows are rejected by the write repository and remain detail-only reconciliation records.

`annual-effort-decimal.js` provides pure integer-hundredths parsing, semantic equality, formatting, and server-equivalent PM rounding. `annual-effort.js` incrementally recalculates the changed participant row, its containing Work Package, and unified project roll-ups; it never submits totals. Event delegation avoids per-cell listeners. Non-sensitive month, expansion, and scroll context uses session storage keyed by project/year; allocation contents are never stored.

The page model classifies each row as equal or divergent. Only equal values contribute once to unified participant/WP/project/PM/capacity summaries. Divergent values contribute neither planned nor actual to those summaries and carry explicit resolution links. Shared `<colgroup>` definitions and common cell containers align every WP and project total structurally.

Work Package repositories apply one shared `NaturalCodeOrder` after deterministic ID retrieval. Registry pagination is sliced after natural ordering, and the same ordered option set drives allocation forms and annual-grid sections.

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
# Workflow-oriented presentation (Milestone 11)

`NavigationService` supplies authorization-filtered sidebar context to the shared SSR layout. `GlobalAnnualOverviewService` builds a dedicated read-only page model from a bounded accessible-project query, one hierarchy query, and one warnings query. This prevents the controller-loop N+1 pattern. The main project URL renders the existing annual model without write metadata; `/projects/{id}/effort/edit` is the explicit server-rendered editing state.

`ProjectCreationController` keeps incomplete wizard state in the authenticated PHP session. `ProjectCreationWorkflowService` performs the final project/WP/participant inserts on one PDO connection and rolls the transaction back on any failure.

## Milestone 11.1 width and sidebar correction

Global, project read-only, project edit, WP-total, and project-total tables all use `.effort-table` and the same colgroup classes. Desktop tables are `width: 100%`, `min-width: 0`, and fixed-layout. The hierarchy/annual budgets are 240/88px above 1500px and 208/80px from 769–1500px; months divide the remaining width. Inputs explicitly use `width: 100%`, `min-width: 0`, and `max-width: 100%`. Only `.effort-grid` and `.project-total-table` own the narrow-screen horizontal fallback.

Bootstrap’s `.offcanvas-md` desktop rule can make the sidebar background transparent with `!important`. `.app-sidebar` therefore defines the application’s explicit dark background with equivalent priority; foreground, muted, hover, active, and focus colors are centralized CSS variables.
