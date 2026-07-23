# Roadmap

Each phase requires migrations, authorization rules, validation, tests, documentation, and a deployable checkpoint.

1. **Users and authentication — completed through milestone 2.1:** unique usernames, email-or-username login, administrator bootstrap, secure login/logout, password hashing/rehashing, session expiry, role guards, and administrator user management. Password-reset email remains future work.
2. **People management — completed:** independent person records, optional one-to-one account links, admin management without deletion, search, filters, pagination, and independent activation.
3. **Project management — completed:** identity, metadata, dates, budget, status, responsible-person ownership, role-based editing, private notes, search, filters, and pagination.
4. **Project participants — completed:** dated person-project associations, primary project roles, independent state, ownership-based management, private notes, search, filters, pagination, and relationship-only removal.
5. **Monthly person-hour allocations — completed:** planned/actual decimal hours, project-specific PM conversion, monthly boundaries, totals, privacy, and historical participant integrity.
6. **Person capacity and monthly overrides — completed:** standard availability, sparse overrides, annual cross-project totals, remaining capacity, and non-blocking warnings.
7. **Work Package registry — completed (Milestone 8A):** project-owned Work Packages, optional responsible participants, date integrity, authorization, privacy, and participant-removal protection.
8. **Allocation-to-Work-Package relationship — completed (Milestone 8B):** multiple WP and unassigned rows per participant/month, precise aggregates, date validation, privacy, and deletion integrity.
9. **Annual effort grid — completed (Milestone 9):** Work-Package-first planned/actual entry, classified totals, capacity context, atomic persistence, and optimistic concurrency.
10. **Mandatory Work Package classification — completed (Milestone 10A):** classified-only new writes, dedicated legacy listing and in-place reclassification, duplicate conflict protection, and aggregate conservation.
11. **Annual effort usability hardening — completed (Milestone 10B):** dirty tracking, decimal-safe previews, keyboard/mobile/WP navigation, filtering, accessible cell states, natural ordering, and context preservation without changing persistence.
12. **Milestone 10B mandatory refinements — completed:** unified person-hours grid writes synchronize retained planned/actual columns, divergent history is protected and excluded, geometry is shared, current month is server-derived, and WP/participant dates receive safe defaults.

Recommended next: classify all remaining legacy rows, then add a guarded migration making `work_package_id` non-nullable only when no normalized legacy rows remain.
9. **Work-Package-first annual effort grid — completed:** planned/actual bulk entry, all participants per WP, classified totals, capacity summaries, atomic persistence, responsive access, and optimistic concurrency.
10. **Project and person reports:** accessible filters, totals, completeness checks, and read authorization.
11. **Audit logging:** actor/action/change metadata, retention, and administrator review.
12. **CSV export:** authorized, formula-injection-safe project/person exports.
13. **Deployment hardening:** CSP, throttling, backup/restore drills, privacy review, monitoring, and Aruba compatibility validation.
# Milestone 11 — workflow and navigation redesign

Implemented: authorization-aware sidebar shell, batch-loaded global annual overview, unified read-only totals, explicit project hour-edit mode, and the transactional four-step project/WP/participant wizard. Recommended follow-up: add automated role-authenticated viewport and query-count instrumentation to complement the current structural and manual checks.
