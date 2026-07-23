# Security

## Assumptions and current controls

The shared host, FTP account, database account, and control panel must be secured by the operator. HTTPS is required in production. The application currently has no login and protects no real records; authentication, role enforcement, rate limiting, and audit logging remain future work.

Sessions use native PHP cookies with `HttpOnly`, `SameSite=Lax`, strict/cookie-only mode, and `Secure` when HTTPS is configured or detected. Future login/logout code must regenerate the session ID, destroy old authenticated state, and use an inactivity/absolute timeout.

Every state-changing form must carry the session CSRF token and every POST controller must validate it before acting. `POST /csrf-test` demonstrates this only outside production. SameSite is defense in depth, not a CSRF substitute.

Templates must escape untrusted values with `View::escape`; raw HTML is permitted only for reviewed, trusted renderer output. SQL must use prepared PDO statements with native prepares. Never interpolate user input into SQL identifiers or clauses; allow-list such values.

Passwords must eventually use `password_hash()` with the current `PASSWORD_DEFAULT`, `password_verify()`, rehash checks, unique accounts, rate limiting, and reset tokens that are random, hashed at rest, expiring, and single-use. Passwords must never be logged or reversibly encrypted.

Production disables displayed errors and stack traces, logs server-side details, and returns generic messages. Health output is limited to application/database availability. Headers block MIME sniffing and framing and restrict browser features.

## Hosting limitations and open risks

Shared hosting limits filesystem isolation, log control, header modules, security patch cadence, and secrets management. Confirm the PHP version/extensions, HTTPS/proxy behavior, document-root capability, Apache overrides, file permissions, database TLS/network location, backups, and log access with Aruba.

Before real data: implement login and role authorization, authorization tests, session expiry, login throttling, audit logs, Content Security Policy, secure backup retention, dependency/security review, privacy/data-retention rules, and an incident procedure. FTP should be replaced by FTPS/SFTP if the plan supports it.
