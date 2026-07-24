# Project effort persistence bug fix

## Root causes

The live browser total was correct because it summed DOM inputs. Persistence and
reload could diverge at two later layers:

1. the original nested annual matrix submitted every input and could exceed PHP
   `max_input_vars`, silently dropping later monthly values;
2. the annual read query used an inner Work Package join and read only
   `work_package_id IS NOT NULL`, so valid project-level rows were persisted but
   excluded when the page was rebuilt.

The narrow final column then combined an hours fragment and PM fragment without a
deliberate layout, causing unreadable wrapping.

## Canonical model and request contract

`person_hour_allocations` monthly rows are the sole source of truth. Both
classifications use the same records:

- Work Package effort: `work_package_id = <valid ID>`;
- Project-level effort: `work_package_id = NULL`, `work_package_key = 0`.

No dummy Work Package or annual aggregate is created. JavaScript sends only changed
cells in one validated `allocations_json` field:

```text
classification key → participant ID → month → decimal hours
```

The server validates every dimension and decimal before the repository transaction.
Missing cells mean unchanged. A positive value is upserted; zero or an intentionally
cleared value removes the sparse row. Invalid values are rejected rather than cast
to zero. The legacy nested payload remains a progressive-enhancement fallback.

The transaction locks the active project and the complete selected-year allocation
snapshot, checks its concurrency token, then inserts, updates or intentionally
deletes only validated changes. Any failure rolls back.

## Read path and totals

The reload query uses a left Work Package join and includes both classifications.
Monthly, annual and project totals are rebuilt from canonical persisted decimals:

```text
total project hours = SUM(project-level monthly hours + WP monthly hours)
PM = total project hours / project.hours_per_pm
```

The configured per-project `hours_per_pm` is the denominator. Hours are summed in
integer hundredths; PM is calculated only after summation and displayed to three
decimal places.

## Visibility and layout

| Project condition | Normal view | Edit view |
| --- | --- | --- |
| No Work Packages | Project-level visible | Project-level visible |
| WP exists, no project-level values | Project-level absent | Available through **Add project-level effort** |
| WP exists, project-level values exist | Project-level visible | Project-level visible |
| Project-level values cleared | Hidden after save | Available through the explicit action |

The final semantic `tfoot` is labelled **Total project effort**. Its summary cell
stacks a primary non-wrapping hours value above a smaller non-wrapping PM value and
uses a wider responsive annual column.

## Database evidence

`AnnualEffortPersistenceIntegrationTest` creates and removes a disposable MySQL
fixture. Its verified lifecycle is:

```text
project-level rows: Jan 10.00, Feb 20.00, Mar 5.50
database/repository/rendered total: 35.50 h
rendered PM at 125 h/PM: 0.284 PM

plus WP1 4.00 and WP2 6.00
database/repository/rendered mixed total: 45.50 h

after clearing project-level rows
remaining database rows: WP1 + WP2
database/repository/rendered total: 10.00 h
```

No schema migration is required; nullable `work_package_id` and `work_package_key=0`
already represent project-level effort and remain compatible with historical data.

## Monthly footer follow-up

The backend aggregation was already correct and canonical: it initializes integer
month keys `1..12`, adds every non-divergent project-level and Work Package value
with decimal-safe arithmetic, and derives the annual total from those twelve sums.

The first layer that lost correct values was browser initialization.
`annual-effort.js` recalculated sections from editable inputs. Read-only pages have
links instead of inputs, so JavaScript replaced the correct PHP-rendered monthly
and annual totals with zero.

The corrected contract is:

- PHP renders twelve `data-month-total="1..12"` cells from
  `projectMonthlyHours`;
- JavaScript preserves server-rendered totals when there is no effort form;
- edit-mode live totals use raw monthly inputs plus persisted non-editable values
  marked with `data-static-effort`, `data-month`, and `data-hours`;
- formatted WP total text is never used as the live aggregation source;
- the twelve raw month sums produce annual hours, which then produce PM.

Automated coverage includes mixed project-level/WP totals (`15`, `13`, `5`),
WP-only, project-level-only, decimal values, zero months, one- and two-digit month
keys, and rendered footer values after a real database save/reload.
