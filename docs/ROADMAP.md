# Roadmap

Each phase requires migrations, authorization rules, validation, tests, documentation, and a deployable checkpoint.

1. **Users and authentication — completed through milestone 2.1:** unique usernames, email-or-username login, administrator bootstrap, secure login/logout, password hashing/rehashing, session expiry, role guards, and administrator user management. Password-reset email remains future work.
2. **People management — completed:** independent person records, optional one-to-one account links, admin management without deletion, search, filters, pagination, and independent activation.
3. **Project management — completed:** identity, metadata, dates, budget, status, responsible-person ownership, role-based editing, private notes, search, filters, and pagination.
4. **Project participants — completed:** dated person-project associations, primary project roles, independent state, ownership-based management, private notes, search, filters, pagination, and relationship-only removal.
5. **Monthly person-month allocations:** precise decimal values, project-month boundaries, totals, and transactional editing.
6. **Project and person reports:** accessible filters, totals, completeness checks, and read authorization.
7. **Audit logging:** actor/action/change metadata, retention, and administrator review.
8. **CSV export:** authorized, formula-injection-safe project/person exports.
9. **Deployment hardening:** CSP, throttling, backup/restore drills, privacy review, monitoring, and Aruba compatibility validation.
