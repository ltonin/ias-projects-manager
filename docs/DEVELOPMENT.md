# Local development

Start a clean environment with:

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
```

MySQL applies migrations `001` through `010` only when creating an empty named volume. For an existing volume, import the missing migration through phpMyAdmin at <http://localhost:8081> and confirm `schema_versions`.

Create the initial admin:

```bash
read -s ADMIN_PASSWORD
export ADMIN_PASSWORD
docker compose exec -e ADMIN_PASSWORD="$ADMIN_PASSWORD" web \
  php bin/create-admin.php --username=local.admin --email=admin@example.test --first-name=Local --last-name=Admin
unset ADMIN_PASSWORD
```

Open <http://localhost:8080/login> and sign in using either email or `local.admin`. Admin user management is at `/admin/users`. Create representative `project_manager`, `participant`, and `viewer` accounts there; a second administrator must be rejected.

Creating a User also creates and links a Person unless an existing unlinked Person is explicitly selected. A same-email Person is never matched silently: confirm the identity and select it in the form. Legacy Users need no schema migration. To link one, open **Administration → People**, create a Person, and choose the unlinked User in **Linked user**. Review the Person fields before saving; User and Person details intentionally remain independent after the initial link.

People management is at `/admin/people`. To create and deliberately link a person:

```bash
docker compose exec web php bin/create-person.php \
  --first-name=Local --last-name=Researcher --position=researcher \
  --email=researcher@example.test --username=local.admin
```

Link the project-manager account to a person before testing project creation. At `/projects`, verify combined search and filters, pagination, admin assignment/reassignment, manager self-ownership, non-owner write denial, participant/viewer read-only access, and that private notes are absent for unauthorized users.

From project details, add linked and unlinked people as participants. Verify the 14 project roles, date-boundary errors, duplicate prevention, list filters, warnings for independent inactive states, private notes, POST activation/deactivation, and confirmed removal. Removing a participant must leave the person, user, and project manager unchanged.

From the read-only annual effort page, verify an administrator or owning project manager can use `Add participant`, while viewers and non-owning managers cannot see or directly access it. The selector contains only active nonparticipants and remains usable as a native select without JavaScript. Creation returns to the same project/year, shows the new participant beneath each Work Package with empty hours, and creates no allocation rows. The retained authenticated regression is `tests/browser/add_project_participant.py`; run it with disposable fixture IDs and the `PARTICIPANT_BROWSER_*` credentials documented in the script environment names.

Use `Configure project` as the post-creation maintenance entry point. Verify the Project details, Work Packages, and Participants navigation works without JavaScript; add and edit a Work Package; add and edit participant metadata; and confirm each operation returns to its registry with any selected year. Empty Work Packages and participants have explanatory states. A new WP and participant must both remain visible in read-only and edit-hours views without creating allocation rows. The retained participant browser regression also covers the canonical configuration tabs and Work Package add/edit path.

From participant details, add planned-only, actual-only, combined, explicit-zero, and above-one-PM allocations. Verify monthly overlap, uniqueness, PM derivation, factor-change warnings, filters, aggregates, note privacy, ownership, and removal. A participant with allocations must remain non-removable.

At `/people/{id}/capacity`, verify 12-month summaries, standard/override sources, positive/zero/negative remaining values, non-blocking warnings, administrator-only override actions, and note omission for non-admins. Confirm capacity remains independent from project conversion factors.

Under a project, test Work Package search, filters, pagination, optional and assigned responsibility, inactive-state warnings, date boundaries, duplicate codes, note privacy, owner/admin writes, read-only access, POST-only removal, project summaries, and participant summaries. Confirm participant removal is blocked while responsibility exists and succeeds after responsibility is cleared when no allocations remain.

For Milestone 8B, create two different Work Package rows and one unassigned row for one participant/month. Confirm same-WP and second-unassigned duplicates fail; Work Package partial-month boundaries work; inactive Work Packages warn but remain usable; participant, project, capacity, Work Package and unassigned totals sum correctly; and Work Package removal is blocked until allocations are removed or reassigned.

At `/projects/{id}/effort`, verify Work Packages remain the top-level hierarchy and every participant appears beneath each relevant Work Package. Test unified person-hours, empty versus explicit zero, date-disabled cells, divergent and inconsistent historical links, incomplete unified totals, capacity warnings, the legacy-unassigned summary, atomic rollback, stale-token rejection, read-only rendering, horizontal scrolling, and narrow-screen access.

For Milestone 10A, verify detailed creation cannot proceed without a project Work Package and rejects null, zero, aliases, and cross-project IDs. Seed legacy rows only as upgrade fixtures, verify `/projects/{id}/allocations/unassigned`, then classify in place. Confirm ID, period, hours, notes, participant/capacity/overall totals are conserved; unassigned totals decrease; classified WP totals increase; and duplicate targets remain separate and unchanged.

For Milestone 10B, use at least three naturally ordered Work Packages and eight participants. Verify empty↔zero dirtiness, `10`↔`10.00` equivalence, integer-safe row/WP/project totals, PM rounding, reset and navigation warnings, Enter/arrow focus, filters that retain dirty rows, expansion badges, month focus from January through December, session context after save, validation focus, and read-only/no-JavaScript output. The validation grid used 288 editable cells (3 WPs × 8 participants × 12 months). Run the retained browser fixture with the command documented in the README/final report; it requires installed headless Chrome but no package installation or build step.

Also verify common fixed colgroups at desktop and mobile widths, server-timezone current-month labels, project-date WP defaults, project/person-intersection participant defaults, dual-column synchronization, one-time aggregation, and divergent-row preservation during unrelated saves.

Run verification:

```bash
docker compose run --rm web composer install
docker compose run --rm web vendor/bin/phpunit
docker compose exec web sh -lc "find app bootstrap bin config public tests views -name '*.php' -exec php -l {} \\;"
docker compose config --quiet
composer validate --strict
```

Tests are isolated and use in-memory repositories unless explicitly documented as database integration tests. Do not point tests at production. Stop with `docker compose down`; use `docker compose down --volumes` only to intentionally erase local database data.
# Workflow UI validation

Validate the shell at 1366×768, 1440×900, and 1920×1080, plus the off-canvas sidebar and month-focused annual grid at 390 px. The overview remains usable without JavaScript through native `details` elements and ordinary links/forms; JavaScript only adds filtering and bulk expansion.

For Milestone 11.1, use `tests/browser/milestone11_1_layout.py` with a disposable authenticated administrator and representative long-name project. The script measures the actual Bootstrap application at device scale factor 1, asserts no document overflow at any supported viewport, asserts no annual-wrapper overflow at 1920 and 1440, checks all twelve months plus Annual, verifies sidebar/main boundaries and current-project state, and calculates rendered sidebar contrast. Screenshots written under `/tmp/m111-*.png` with `--screenshots` are disposable inspection artifacts and must not be committed.

The same fixture validates the 248 px expanded sidebar and 56 px desktop rail, preference restoration through the non-sensitive `iaspm.sidebar` local-storage key, keyboard toggling, focus tooltips, temporary Projects expansion, no-JavaScript expanded fallback, and the independent 248 px mobile off-canvas. The rail uses the repository's inline outline-SVG symbol set and naturally releases 192 px to the workspace without scripting table widths.

Overview years are canonical query parameters (`/?year=YYYY`). The sidebar deliberately continues to list every accessible project, while the overview itself shows only accessible projects whose date interval overlaps the selected year. `/capacity?year=YYYY` provides the authorized batched capacity overview; its Person panels remain expanded without JavaScript.
