# Milestone 19 — Safe Project Deletion and Trash Management

## Architecture

Migration `012_add_project_trash.sql` adds nullable `projects.deleted_at` and
`projects.deleted_by_user_id`. Deletion remains independent from the business
`status`. Existing projects are backfilled implicitly as active because both fields
default to `NULL`.

Ordinary `ProjectRepository` reads (`findById`, search, navigation/year scopes)
exclude deleted projects centrally. `findIncludingDeleted` exists only for the
administrator Trash workflow. Consequently direct operational project URLs return
404 after soft deletion and all nested configuration controllers also lose access.

Capacity totals and cross-project annual warnings explicitly join active projects.
Allocations, Work Packages and participants remain stored unchanged and contribute
again immediately after restoration. Operational and historical UI totals both
exclude Trash; the read-only Deleted Project summary is the retained-data view.

## Authorization

| Action | Administrator | Authorized Project Manager | Other user |
| --- | ---: | ---: | ---: |
| Move to Trash | Yes | Yes | No |
| View Deleted projects | Yes | No | No |
| Restore project | Yes | No | No |
| Delete permanently | Yes | No | No |

All mutations are POST-only, CSRF-protected and authorized server-side. Move to
Trash rechecks project ownership in the database update. Permanent deletion accepts
only a soft-deleted project and requires an exact acronym confirmation on both the
client and server.

## Dependency map and transaction

| Dependency | Soft deletion | Permanent deletion |
| --- | --- | --- |
| `person_hour_allocations` | retained, excluded operationally | explicitly deleted first |
| `work_packages.responsible_participant_id` | retained | cleared before participant deletion |
| `work_packages` | retained | explicitly deleted |
| `project_participants` | retained | explicitly deleted |
| `projects` | marked deleted | explicitly deleted last |
| `people`, users, capacity overrides | retained | retained; not project-owned |
| `project_deletion_audit` | independent record | retained permanently |

Permanent deletion locks the project and performs every step plus the audit insert
inside one transaction. Any exception rolls the complete operation back. The audit
stores the former project ID, display name, stable code, acting user, timestamp and
dependent-record counts without a foreign key to the project.

Project identifiers remain protected by the existing unique constraints while a
project is in Trash. This prevents a new project from taking its acronym/internal
code and makes restoration conflict-free. Restoration also verifies that its
responsible Person still exists and that project dates remain structurally valid.

## UI and system diagnostics

Configure Project → Details includes a separated Danger zone. Administrators have a
compact `Deleted projects` navigation item with list, read-only summary, restore and
permanent-delete confirmation. The permanent page recommends downloading the
existing streaming SQL backup. System statistics distinguish active and deleted
projects.

## Deployment on Aruba

1. Download a complete SQL backup from Administration → System.
2. Apply `database/migrations/012_add_project_trash.sql`.
3. Deploy application files.
4. Confirm schema version `012` in Administration → System.
5. Smoke-test project lists, Capacity and Trash using disposable staging data.
6. Run the authenticated Administrator and Project Manager scenarios described in
   the milestone at desktop and 390×844.

The migration is additive and does not delete or rewrite an existing project.
Rollback requires restoring the pre-deployment SQL backup after reverting the
application; dropping the audit table/columns manually would discard audit history
and is therefore not recommended.

## Known limitations

The current application has no project attachments, comments, exports or separate
annual-allocation table. These dependencies are therefore not applicable. Browser
acceptance requires configured staging credentials and disposable project data; it
must be completed during the staging smoke test and must never target production
data.
