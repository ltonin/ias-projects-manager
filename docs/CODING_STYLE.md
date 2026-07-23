# Coding style

- Use PHP strict types, `App\` PSR-4 organization, four spaces, English names/comments, typed parameters/returns/properties, explicit visibility, and final classes unless inheritance is intentional.
- Prefer small cohesive methods, constructor injection, `DateTimeImmutable`, domain-specific exceptions, and clear failure over hidden fallback.
- Controllers receive requests, enforce authentication/authorization, invoke validation/services, and render or redirect. They contain no SQL or business rules.
- Services own use cases, business rules, and transaction boundaries. Repositories own prepared PDO queries and persistence mapping. Views contain presentation only.
- Validate all external input with reusable validators and return field-specific errors. Never trust route, form, query, session, or database values merely because they are typed.
- Catch exceptions only when adding context, translating at a boundary, rolling back, or producing an HTTP response. Never suppress errors or expose secrets/traces.
- Add tests for behavior and regression risk. Update architecture, security, database, deployment, and roadmap documents when their contracts change.
- Do not add a production package, framework, ORM, template engine, frontend build system, or CDN dependency without documented necessity and shared-hosting compatibility. Composer remains development-only.
