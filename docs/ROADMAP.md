# Roadmap

Each phase requires migrations, authorization rules, validation, tests, documentation, and a deployable checkpoint.

1. **Users and authentication:** administrator bootstrap, secure login/logout, password lifecycle, session expiry, and role foundation (`admin`, `participant`, `viewer`).
2. **People management:** person records separated from optional application accounts.
3. **Project management:** project identity, metadata, dates, status, and administration.
4. **Project participants:** dated associations, roles, uniqueness, and integrity rules.
5. **Monthly person-month allocations:** precise decimal values, project-month boundaries, totals, and transactional editing.
6. **Project and person reports:** accessible filters, totals, completeness checks, and read authorization.
7. **Audit logging:** actor/action/change metadata, retention, and administrator review.
8. **CSV export:** authorized, formula-injection-safe project/person exports.
9. **Deployment hardening:** CSP, throttling, backup/restore drills, privacy review, monitoring, and Aruba compatibility validation.
