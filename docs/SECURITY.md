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

Production disables displayed errors and stack traces, logs server-side details, and returns generic messages. Health output is limited to application/database availability. Headers block MIME sniffing and framing and restrict browser features.

## Hosting limitations and open risks

Shared hosting limits filesystem isolation, log control, header modules, security patch cadence, and secrets management. Confirm the PHP version/extensions, HTTPS/proxy behavior, document-root capability, Apache overrides, file permissions, database TLS/network location, backups, and log access with Aruba.

Before sensitive real data: add login throttling, password-reset workflow, audit logs, Content Security Policy, secure backup retention, dependency/security review, privacy/data-retention rules, and an incident procedure. FTP should be replaced by FTPS/SFTP if the plan supports it.
