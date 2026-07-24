# Milestone 13 workflow and authorization audit

## Previous workflow

The application had two project-detail concepts. `/projects/{id}` already rendered the
annual effort page as a read-only overview, while the older `ProjectController::show`
and `views/projects/show.php` remained in the tree but were not routed. Creation used
the four-step `/projects/create` workflow. Structural maintenance used
`/projects/{id}/configure`, `/projects/{id}/work-packages`, and
`/projects/{id}/participants`; effort used `/projects/{id}/effort/edit`.

The configuration registries already shared `_configure_nav.php`, but the overview
also offered a participant quick-add action. The old, unrouted project view exposed a
second set of “Edit”, “Add participant”, “Create Work Package”, and effort links.
Generated links use `UrlGenerator`, so the deployment base path is preserved.
Configuration forms preserve `year` through validated integer return fields.

## Canonical workflow

* `/projects` — searchable project list and creation entry point.
* `/projects/create` — project details, Work Packages, participants, review.
* `/projects/{id}?year=YYYY` — read-only annual project overview.
* `/projects/{id}/configure?year=YYYY` — Project details.
* `/projects/{id}/work-packages?year=YYYY` — configuration Work Packages section.
* `/projects/{id}/participants?year=YYYY` — configuration Participants section.
* `/projects/{id}/effort/edit?year=YYYY` — monthly effort editing only.

The legacy `/projects/{id}/effort` URL remains a compatibility redirect to the
read-only overview. The old project detail template is retained only as unused
legacy code; no canonical navigation links to it.

## Authorization

| Capability | Administrator | Project Manager |
| --- | --- | --- |
| View every project | Yes | Yes |
| View People/search | Yes | Yes |
| Create/edit/delete People | Yes | No |
| Manage users | Yes | No |
| Configure every project | Yes | No |
| Configure owned project | Yes | Yes |
| Edit hours on owned project | Yes | Yes |

`ProjectPolicy` separates global Project Manager read access from owner-only
mutation methods. `Authorization::peopleViewer()` centralizes People list access;
all People mutation endpoints still require `admin()`.

## Root causes and corrections

Project navigation and annual overview called `accessibleFor*()`, whose SQL treated
Project Managers like participants and filtered by manager/participant membership.
Project Managers are now in the global read branch, while repository update guards
and project policies remain owner-specific.

People navigation and the People list controller were both hard-coded to
administrator role checks. Project Managers now receive a read-only list with
search/filter support; administrative actions and user management remain hidden
and server-side protected.

Project creation rendered exactly five rows. The collection now renders submitted
rows without truncation, starts with one empty row, supports removing rows in the
browser, and provides a server-rendered “Add another Work Package” submit action.
Indexed validation keys identify the affected row.

Capacity calculation previously reduced twelve comparisons to `monthsOver`.
`AnnualEffortService` now exposes each over-capacity year/month with allocated,
capacity, and excess hours. The project overview renders a compact per-person list
and omits unaffected months.

`WorkPackage::warnings()` generated “No responsible participant is assigned.”
That warning was removed without changing the optional responsibility field or
other inactive-responsible warnings.

No database migration is required.
