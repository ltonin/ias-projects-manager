# Milestone 14 implementation report

## Navigation audit and final flow

The project overview linked participant names directly to the allocation registry
with only `year` and WP filters. That registry always linked back to participant
detail. Configuration details used a hard-coded Projects-list return, while child
forms used separate year/return flags.

Allocation links now send the whitelisted context name `project`; the controller
accepts only `project`, `capacity`, or its default participant context and validates
the year before generating a URL. No return URL or HTTP referrer is accepted.

The canonical flow is:

* Project overview → Person-Month Allocation → same project overview and year.
* Project overview → Configure Project → Details, Work Packages, Participants →
  same project overview and year.
* Participants → Add/Edit → Participants for the same project and year.
* Work Packages → Add/Edit → Work Packages for the same project and year.

The shared configuration navigation now supplies the project/year header, an
explicit Project Overview return, and real-link Details, Work Packages, and
Participants tabs with `aria-current`.

## Annual capacity

Migration `011_add_annual_person_capacity.sql` adds required
`people.annual_capacity_hours DECIMAL(8,2)`. It backfills these stable identifiers
to 1150 hours: `full_professor`, `associate_professor`, `assistant_professor`, and
`researcher`. All other identifiers, including `postdoc`, `phd_student`,
`research_fellow`, technical/administrative roles, external collaborators, and
`other`, receive 1500 hours.

Create forms propose the role default until the field is manually changed. Edit
forms preserve the stored value when the role changes. Only administrator mutation
routes can save the field; Project Managers see it in the read-only People list.

Standard monthly capacity is `ROUND(annual_capacity_hours / 12, 2)`. Existing
month-specific overrides still take precedence. Project capacity warnings and the
global Capacity service both use this derivation. The former legacy monthly field
is retained for schema compatibility but is no longer an authoritative capacity
source.

For Aruba, back up the database and import
`database/migrations/011_add_annual_person_capacity.sql` once through phpMyAdmin,
then verify schema version `011`.

## UI cleanup

The configuration pills were replaced with compact underlined secondary
navigation. The Capacity toolbar stretch came from grid-item stretching; its
buttons now use `width: max-content` and `justify-self: start`, with wrapping
retained at mobile width.

No route migration is required and all URLs continue through `UrlGenerator`, so
`APP_BASE_PATH` remains supported.
