# MBLOGISTICS POS V5 — Responsive and Platform QA Matrix

## Purpose

Use this matrix for every shared-shell change and every modernized route. Passing one desktop screenshot is not sufficient. Record browser, operating system, viewport, role, language, display mode, route, and result for each run.

## Required viewport matrix

| Class | CSS viewport | Primary checks |
| --- | ---: | --- |
| Wide desktop | 1440 × 900 | Expanded sidebar, content width, dense tables, sticky header |
| Desktop/laptop | 1280 × 800 | Navigation groups, page actions, filters, account menu |
| Compact desktop | 1024 × 768 | Collapsed sidebar, search adaptation, table containment |
| Tablet portrait | 768 × 1024 | Rail/drawer transition, two-to-one-column forms, touch targets |
| Large mobile | 425 × 900 | Mobile top/bottom navigation, safe actions, readable cards/tables |
| Standard mobile | 375 × 812 | iPhone-class safe areas, keyboard, translated label growth |
| Narrow mobile | 325 × 700 | No clipping/overflow, action wrapping, minimum viable density |

Also test landscape at 844 × 390 or the closest available device size. Fixed navigation must not consume most of the usable height.

At each mobile width, test both browser and installed/standalone behavior where supported, with short and long English/Myanmar labels, the virtual keyboard open, a drawer or sheet open, a validation error visible, and unsaved form data present.

## Platform/browser matrix

| Platform | Browser/mode | Required behavior |
| --- | --- | --- |
| Windows 11 | Current Chrome and Edge | Pointer/keyboard parity, PWA install, native date/select controls, printing unaffected |
| macOS | Current Chrome and Safari | Keyboard shortcuts, focus, sticky shell, responsive resize |
| iOS current and previous major | Safari browser | 16 px inputs, no forced zoom lock, safe areas, install instructions, virtual keyboard |
| iOS | Installed standalone PWA | Status bar, top/bottom safe areas, relaunch route, offline page, update behavior |
| Android current | Chrome browser | Native install prompt, back behavior, virtual keyboard, touch/table scrolling |
| Android | Installed standalone PWA | App display mode, navigation, offline state, update behavior, system back |

Use feature detection in code. This matrix verifies outcomes; it does not justify separate business logic by operating system.

## Roles

Test at least:

- Developer;
- ADMIN;
- Staff;
- Myanmar operations user;
- Malay operations user;
- unauthenticated/expired session.

For each role:

- visible navigation matches permission;
- direct URL access is independently authorized;
- branch/region records are correctly scoped;
- submitted IDs outside scope are rejected;
- developer diagnostics never appear for operational users.

## Languages

Run the complete core journey in both English and Myanmar:

1. Sign in.
2. Navigate with desktop/sidebar or mobile drawer.
3. Open Create Voucher.
4. Enter New Sender and New Receiver.
5. Add multiple items and inspect totals.
6. Review without submitting against production.
7. Open ledger, shipments, voucher view, finance, and configuration routes allowed for the role.
8. Trigger representative empty, validation, offline, and retry states in a safe environment.

Check for untranslated labels, clipped Myanmar text, incorrect line-height, mixed-language placeholders, translated user data, and layout movement during language switching.

## Shared shell assertions

- Exactly one visible global header.
- Exactly one primary navigation appropriate to the viewport.
- Current route has `aria-current="page"`.
- Mobile drawer opens, traps focus, closes with Escape, restores focus, and prevents background scroll.
- Bottom navigation does not cover content or page actions.
- Sidebar state persists without flashing the wrong layout.
- Account menu has an understandable Logout action.
- Notification badge is readable at 1–999+ and does not distort the header.
- Online state is quiet; offline/degraded state is prominent and truthful.
- No Customers, public website, CMS portal, customer portal, old POS, or site-management entries appear in the target V5 navigation.
- No emoji, Unicode symbols, ASCII glyphs, or remote images are used as shell/control icons. All interface icons come from the approved local SVG system and expose correct accessible names.

## Responsive assertions

- No horizontal scrolling on `html` or `body`.
- Any horizontal table scroll is contained and keyboard/touch accessible.
- No text, buttons, badges, menus, or dialogs are clipped.
- Primary actions remain visible and at least 44 × 44 px.
- Inputs use at least 16 px text on mobile.
- Content remains usable at 200% browser zoom.
- Device rotation preserves work and does not strand overlays.
- Virtual keyboard does not hide the focused field or primary form action.
- iOS and Android standalone modes respect safe-area insets.
- Fixed/sticky UI uses dynamic viewport and safe-area measurements and remains correct when mobile browser chrome expands/collapses.
- Android system/predictive Back and iOS swipe-back close navigation overlays or follow route history predictably without losing work.
- Bottom sheets, drawers, dialogs, bottom navigation, sticky actions, toast regions, and the virtual keyboard never overlap critical content.

## Accessibility assertions

- One logical `h1` per page and ordered headings.
- Skip link reaches the main content.
- All fields have programmatic labels; required state and errors are announced.
- All controls are keyboard reachable in logical order.
- Focus is always visible and never trapped outside an intentional modal/drawer.
- Icon-only controls have meaningful accessible names.
- Status is not conveyed by color alone.
- Reduced-motion mode removes nonessential transitions/animation.
- Screen-reader reading order matches the visual workflow.
- Charts have text/table equivalents.

## Data and security assertions

- Page source does not expose credentials, stack traces, server paths, diagnostic tokens, or raw SQL errors.
- POST without valid same-origin/CSRF protection is rejected.
- GET requests do not delete, toggle, or update data.
- Bulk actions reject more than the server limit and IDs outside role scope.
- Duplicate submission is prevented in UI and remains safe on the server.
- Currency, amount, weight, date, ID, and enum validation occurs server-side.
- User-entered content renders escaped.
- Customer UI removal does not delete or corrupt legacy voucher/customer data.
- Voucher print regression comparison is unchanged.

## Blank-form and real-data assertions

Inspect the DOM and rendered UI for every create/add route, especially Voucher Create:

- sender and receiver names, phones, and addresses are blank;
- origin, destination, branch, delivery type, currency, payment state, and status are unselected unless a documented mandatory server rule applies;
- charges, discounts, weights, prices, quantities, totals, and dates are blank rather than `0`, today, or a sample value;
- notes and descriptions are blank;
- item rows contain no sample category or calculated-looking value;
- placeholders contain no fake names, phone numbers, addresses, tracking numbers, weights, prices, amounts, or dates;
- no first database option becomes selected merely because it is first;
- browser autofill does not leak a previous voucher’s operational values into a new voucher;
- validation identifies missing required values without inserting them;
- edit routes populate only real persisted record data;
- filters restore only values explicitly present in the URL/session preference contract;
- empty-state screenshots and development fixtures do not ship as production operational data.

## Network, cache, and PWA assertions

Test online, slow connection, request timeout, offline, reconnect, service-worker update, Redis unavailable, and server error conditions.

- Authenticated PHP and API responses are not stored in Cache API.
- Static assets use the current version and old caches are removed safely.
- Offline navigation shows the neutral offline page, never a stale private record.
- State-changing actions are unavailable or clearly blocked offline.
- Reconnect does not auto-submit stale forms.
- A service-worker update does not discard unsaved voucher work.
- Redis failure falls back to the database without exposing an error to the operator or reporting a false cache success.
- Notification polling pauses while hidden and does not replay historical events as fresh alerts.

## Page-level smoke checks

| Route area | Key checks |
| --- | --- |
| Login | Session error, password manager, keyboard, install help, no internal diagnostics |
| Dashboard | Useful operational summary, no greeting/shift/system-load clutter, quick actions |
| Create Voucher | New sender/receiver only, item add/remove, totals, validation, unsaved work, review |
| Voucher Ledger | Pagination, filters, export, no 500-row accessibility/performance freeze |
| Bulk Update | Selection count, 200 limit, permission/scope enforcement, confirmation/result |
| Shipments | Region/branch scope, filters, status display, contained mobile table/list |
| Voucher View | Correct data hierarchy, status story, print link, no print mutation |
| Finance | Currency-separated totals, date filters, precision, safe edit/delete |
| Configuration | CRUD state, dependency warning, active/inactive meaning, developer/admin only |
| Notifications | Initial load does not replay old toasts, read state, empty/error behavior |
| Maintenance | Developer-only, explicit impact, contextual confirmation, no secret diagnostics |

For Create Voucher, repeat the smoke test from a fresh session/storage state and verify every operator-controlled field is blank before the first interaction.

## Component and hook assertions

- Repeated patterns render from shared PHP components/partials rather than copied route markup.
- `data-ui` behavior hooks initialize once and remain safe if initialization runs again.
- Dynamically added item rows receive behavior through delegation without duplicate listeners.
- Components define and display loading, empty, error, disabled, offline, and success states consistently.
- Timers, observers, and in-flight requests stop when no longer needed.
- The URL remains the source of truth for shareable filters and pagination.
- Client previews never override authoritative server totals, permissions, validation, or status rules.
- No React/framework runtime is introduced solely to simulate hooks or components.

## Static verification

```sh
find pos -path 'pos/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check pos/assets/js/main.js
node --check pos/sw.js
git diff --check
```

Treat warnings, console errors, uncaught promises, mixed-content requests, missing assets, and service-worker exceptions as failures until explained and documented.

Also run project searches for prohibited UI patterns and review every match rather than deleting blindly:

```sh
rg -n "[😀-🙏🌀-🫿]" pos --glob '!vendor/**'
rg -n "value=|selected|checked|placeholder=" pos --glob '*.php' --glob '!vendor/**'
rg -n "customer|public_website|cms|old.pos|website.preview" pos/templates pos/assets pos/*.php
```

The value/selected/placeholder search is an audit aid. Legitimate edit values, CSRF fields, record IDs, filter restoration, and protocol values remain valid.

## QA evidence template

For each release, record:

```text
Commit:
Environment:
Role:
Language:
Platform/browser:
Viewport/display mode:
Routes checked:
Automated checks:
Visual checks:
Data mutations performed:
Print regression result:
Known limitations:
```
