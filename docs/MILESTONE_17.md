# Milestone 17 — Complete UI/UX polish

## Audit

All 32 PHP views, the shared layout, application CSS, JavaScript-enhanced annual
grid, and authenticated browser routes were reviewed.

The recurring problems were:

- list filters presented as visually heavy cards;
- Bootstrap table borders and colored totals competing with the data;
- tight rows and inconsistent header weight;
- large groups of Edit, Capacity, Activate, and Deactivate buttons;
- solid bordeaux navigation dominating every page;
- secondary and inactive states rendered as badges;
- inconsistent button sizing on tablet/mobile, including full-width Reset and
  Filter actions;
- detail labels carrying too much bold weight;
- dashboard/project totals receiving the same visual weight as warnings;
- page-specific spacing rules producing different visual rhythms.

Forms were structurally consistent and accessible, but their card shadows,
labels, helper text, and focus rings needed a common treatment. Wide annual and
administrative tables legitimately require an internal horizontal scroller; the
document itself must never overflow.

## Before and after rationale

The interface previously used strong borders and cards as the default container.
The redesign keeps the recognizable bordeaux navigation while treating the
brand color as an accent throughout the workspace:

- the sidebar retains the distinctive UNIPD bordeaux requested for the product,
  with restrained darker hover/active states and white navigation text;
- the application header is white with a subtle neutral divider;
- primary actions, current context, links, and keyboard focus carry the brand;
- ordinary content uses white surfaces and `#e5e8eb`/`#eef0f2` separators;
- warnings and errors retain semantic color.

This creates a clear order: page title, optional toolbar, primary content, then
secondary detail. KPI cards remain cards; filter groups become light toolbars;
tables and detail sections no longer compete as equally elevated panels.

## Standardized components

### Typography and spacing

Page titles use a responsive 28–36 px scale, slightly tighter tracking, and a
650 weight. Subtitles and helper text share one muted color. Section headings,
definition-list labels, page padding, and vertical section spacing now use a
single hierarchy.

### Tables

Every Bootstrap table inherits:

- light gray headers without strong brand fills;
- 11–12 px vertical cell padding;
- subtle zebra rows and separators;
- consistent vertical alignment and tabular numerals;
- border removal for previously boxed `table-bordered` grids;
- compact, right-aligned action columns;
- local responsive scrolling without document overflow.

Current-month highlighting remains deliberately subtle. Project totals now use a
neutral surface and thin divider instead of a colored block.

### Toolbars and actions

Project, People, participant, and allocation filters use the shared
`filter-toolbar`. Existing Overview and Capacity toolbars receive the same
surface, label, spacing, and responsive rules.

People and Users now expose one accessible contextual Actions menu per row.
Edit/Capacity and state changes remain available, while only the page-level
create action stays directly visible. Each menu trigger has a row-specific ARIA
label.

### Forms and feedback

Controls share height, border, hover, focus, helper-text, and validation
treatment. Form cards have no shadow and use responsive internal spacing.
Alerts are quiet white/near-white notices with a semantic left rule. Empty
states, pagination, dropdowns, and disabled maintenance actions use the same
radius and spacing system.

### Badges

Active, warning, error, and operational states remain badges. Inactive is plain
muted text. Roles remain plain text throughout management and profile pages.

## Pages simplified

- Overview and annual effort grids
- Projects registry and project summaries
- People and Users administration
- Project Participants and Work Packages
- Allocation lists, detail, and forms
- Capacity overview and personal capacity
- User Profile
- System operations dashboard
- Authentication, empty, warning, removal, and error states through the shared
  component layer

No routes, policies, validation rules, persistence behavior, or business
calculations changed.

## Accessibility and responsive validation

Keyboard focus uses a consistent 3 px bordeaux outline with offset. Contextual
menus use native Bootstrap keyboard behavior and explicit labels. Existing
semantic tables, headings, form labels, live regions, and navigation landmarks
were preserved.

Authenticated browser review ran against an isolated disposable database and web
container, never touching existing accounts. It covered:

- Administrator: 8 routes;
- Project Manager: 7 routes;
- User/viewer: 4 routes;
- 1440 px desktop, 768 px tablet, and 390 px mobile.

The 57 resulting page states/screenshots were checked for:

- document overflow;
- buttons wider than 300 px;
- keyboard-visible focus;
- HTTP/authorization errors;
- light table-header colors;
- filter and action-menu rendering.

The first review found oversized mobile Reset and tablet Filter buttons. Both
were corrected and the complete three-role run then passed. Review screenshots
and JSON measurements were generated under `/tmp/m17-screens` and
`/tmp/m17-{admin,manager,user}.json`.

The final PHPUnit run passed 161 tests and 754 assertions with 4 warning notices.

## Files

- `public/assets/css/app.css`
- `views/projects/index.php`
- `views/admin/people/index.php`
- `views/admin/users/index.php`
- `views/project_participants/index.php`
- `views/person_hour_allocations/index.php`
- `tests/browser/milestone17_polish.py`
- `tests/Unit/Milestone17UiPolishTest.php`
- `docs/MILESTONE_17.md`
