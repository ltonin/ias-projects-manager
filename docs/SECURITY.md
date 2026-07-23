# Security

## Assumptions and current controls

The shared host, FTP account, database account, and control panel must be secured by the operator. HTTPS is required in production. The application implements session login by normalized email or username and the exact roles `admin`, `project_manager`, `participant`, and `viewer`; only the single administrator manages users and people. Login throttling, password-reset email, two-factor authentication, and audit logging remain future work.

Sessions use native PHP cookies with `HttpOnly`, `SameSite=Lax`, strict/cookie-only mode, and `Secure` when HTTPS is configured or detected. Login/logout regenerate the ID. Only user ID and authentication/activity timestamps are stored. Configurable idle/absolute timeouts invalidate authentication; missing and inactive accounts are also invalidated.

Every state-changing form must carry the session CSRF token and every POST controller must validate it before acting. `POST /csrf-test` demonstrates this only outside production. SameSite is defense in depth, not a CSRF substitute.

Templates must escape untrusted values with `View::escape`; raw HTML is permitted only for reviewed, trusted renderer output. SQL must use prepared PDO statements with native prepares. Never interpolate user input into SQL identifiers or clauses; allow-list such values.

Passwords use `password_hash()` with `PASSWORD_DEFAULT`, `password_verify()`, and successful-login rehash checks. The default policy is 12–4096 characters with no arbitrary composition rules. Passwords and hashes never enter sessions, views, logs, or repopulated forms. Future reset tokens must be random, hashed at rest, expiring, and single-use.

Usernames cannot contain `@`, so identifiers containing `@` are unambiguously email candidates. All other identifiers are canonical lowercase username candidates. Malformed, unknown, inactive, and wrong-password attempts use the same generic error. Neither usernames nor submitted login identifiers are stored in session state.

People routes are admin-only and all writes are POST plus CSRF. Person values are escaped at output and persisted through prepared statements. Notes may contain sensitive administrative information: they appear only on the administrator edit form, never in lists, navigation, HTML comments, or logs. Person and account fields and active states are not synchronized.

Project routes require authentication and project writes require CSRF. The administrator can manage all projects. A project manager needs a linked person and can manage only rows whose `manager_person_id` matches that person; services ignore/reject crafted ownership changes and repository updates repeat the ownership condition atomically. Participants, viewers, non-owners, and unlinked managers are read-only. Project notes are removed from unauthorized detail view models and all list models, not merely hidden with CSS.

Participant reads require any active authenticated account. Writes and removal require CSRF plus administrator or current owning-manager authorization. The service reloads current ownership, and each PDO write repeats ownership in its SQL condition, protecting stale forms and crafted project IDs. Participant IDs must belong to the route project; person and project identities cannot be changed by edit input.

The unique project/person constraint protects against duplicate races. Search wildcards are escaped and all values are bound. Participation notes are removed from every list object and from unauthorized detail models. Removal deletes only the relationship and never the person, linked user, or ownership field.

Allocation reads require authentication. Writes require CSRF and current administrator or owning-manager authorization, rechecked in services and conditional SQL. Project, participant, and allocation IDs must form the current hierarchy. Decimal inputs reject signs, exponent notation, separators, excess precision, and overflow.

Allocation notes are removed from list objects and unauthorized detail models. SQL aggregates never include notes. Participant deletion is blocked while allocations exist.

Numeric capacity and cross-project totals are visible to authenticated users. Standard-capacity and override writes are administrator-only, POST actions require CSRF, and override IDs must belong to the route person. Override notes are administrator-only and omitted from annual models, non-admin pages, allocation context, and logs.

Work Package reads require authentication. Writes require administrator or current owning-manager authorization, CSRF, route-project consistency, and ownership repeated in conditional SQL. Responsible IDs are project-participant IDs—not person IDs—and conditional writes reject cross-project assignments. Duplicate codes are protected against races by a case-insensitive project/code unique constraint.

Private Work Package notes are absent from lists and unauthorized detail models. Participant deletion is blocked by the restrictive foreign key and service-level conflict whenever Work Package responsibility exists. Work Package deletion removes only that Work Package.

Allocation Work Package IDs are validated against the route project and repeated in conditional create/update SQL. Work Package association grants no authorization: responsibility alone never permits allocation writes. Allocation notes remain absent from list, Work Package summary, and unauthorized detail models.

Normal allocation creation and assigned edits reject empty, zero, alias, malformed, null, cross-project, and client-supplied normalized-key values. Legacy reclassification accepts only a Work Package ID, preserves protected allocation fields server-side, rechecks current ownership during POST and in conditional SQL, and never merges a duplicate target. The project-level legacy list exposes only a notes-presence indicator; private text remains restricted to administrator and owning-manager detail views.

The normalized allocation key and database unique constraint protect both assigned and unassigned duplicate races. Work Package deletion is blocked by service checks and the restrictive allocation foreign key while any allocation references it.

Annual-grid reads require authentication. Only administrators and current owning project managers receive inputs, CSRF tokens, concurrency tokens, or bulk payload names. POST rechecks current ownership and validates every numeric Work Package, participant, and month key against project-derived sets. Null, zero, aliases, malformed nesting, decimal overflow, and oversized payloads are rejected.

Bulk persistence is transactional, locks relevant records, and rejects stale snapshot tokens without partial writes. Annual page models contain no allocation, Work Package, or capacity-override notes. Existing detailed pages remain the protected path for note editing.

The unified grid accepts only one nested person-hours value. Server code derives both stored columns and ignores unrelated top-level planned/actual fields. Locked divergent rows reject forged grid updates and are never silently unified; detailed allocation authorization remains the only reconciliation path.

Milestone 10B client calculations are display-only and are never named or submitted as authoritative fields. The enhancement reads only already-rendered allocation hours, public participant/WP labels, and the project PM factor. It stores only selected month, expanded WP IDs, and scroll position in session storage—never allocation values or private notes. Read-only markup contains no allocation input names, dirty/reset/save controls, CSRF token, or concurrency token. Disabling JavaScript preserves every server-side protection.

Production disables displayed errors and stack traces, logs server-side details, and returns generic messages. Health output is limited to application/database availability. Headers block MIME sniffing and framing and restrict browser features.

## Hosting limitations and open risks

Shared hosting limits filesystem isolation, log control, header modules, security patch cadence, and secrets management. Confirm the PHP version/extensions, HTTPS/proxy behavior, document-root capability, Apache overrides, file permissions, database TLS/network location, backups, and log access with Aruba.

Before sensitive real data: add login throttling, password-reset workflow, audit logs, Content Security Policy, secure backup retention, dependency/security review, privacy/data-retention rules, and an incident procedure. FTP should be replaced by FTPS/SFTP if the plan supports it.
# Milestone 11 navigation and read-only boundaries

Project names in the sidebar and global overview come only from the server-side accessible-project query. Direct project and edit routes recheck access independently of navigation visibility. The read-only project response receives neither an hour-edit CSRF token nor an optimistic-concurrency token and contains no hour inputs. The explicit edit route is restricted to administrators and the current owning project manager.

Every project-creation transition requires authentication, creation authorization, and CSRF validation. Workflow state is server-side and bound to the current user ID. Manager ownership is imposed for project-manager accounts; people IDs and duplicate selection are revalidated at final confirmation. A failed child insert rolls back the project and every preceding child insert.
