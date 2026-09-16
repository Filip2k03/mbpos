# MBLOGISTICS POS V5 — Frontend Build Plan

## Goal

Deliver one production-quality responsive frontend across every active POS route, using a dynamic role-aware sidebar, one shared header, explicit English/Myanmar support, safe PWA behavior, and the existing PHP/MySQL backend contracts.

This plan is sequenced so each phase can ship without breaking operations.

The implementation agent must work through all phases and route gates. “Finished” means the route inventory is exhausted and the definition of done passes—not that one flagship screen looks modern.

## Phase 0 — Baseline and inventory

- Record the current Git commit and dirty worktree.
- Capture screenshots at 1440, 1024, 768, 425, 375, and 325 px for every routed page.
- Inventory every route in `pos/index.php`, its allowed roles, forms, destructive actions, client dependencies, table size, and current loading/error/empty behavior.
- Identify orphaned routes and UI for customer features, portals, CMS, and old POS surfaces.
- Record the current voucher print at representative data lengths for regression comparison.
- Measure current asset count, transferred bytes, PHP response time, layout shifts, and accessibility issues.
- Inventory all hard-coded `value`, `selected`, `checked`, sample placeholder, fake record, emoji, Unicode icon, duplicated component, and page-local behavior patterns.

Deliverable: route inventory and before-state evidence attached to the implementation handoff. Do not mutate the database during baseline capture.

## Phase 1 — Canonical shell and dynamic navigation

- Create one PHP navigation registry with role, route aliases, group, icon, mobile priority, and optional badge metadata.
- Render desktop sidebar, tablet rail, mobile drawer, and bottom navigation from the registry.
- Implement sidebar expanded/collapsed state, focus-safe mobile drawer, active route state, tooltips, and scroll behavior.
- Consolidate to one top utility bar and remove legacy V3 inline shell styling after shared styles are complete.
- Remove Customers and legacy portal links from the target shell.
- Add a clear account menu and connection state.
- Replace all shell emoji/text symbols with the approved local SVG icon system.
- Keep route authorization server-side and test direct URL access by role.

Acceptance:

- Exactly one header and one primary navigation system is visible at every viewport.
- No navigation item points to an unavailable or unauthorized route.
- Desktop, tablet, and mobile variants are generated from the same registry.
- Browser zoom and text scaling remain enabled.

## Phase 2 — Design tokens and shared components

- Normalize color, typography, spacing, radius, elevation, focus, motion, and z-index tokens.
- Build shared page header, panel, toolbar, filter grid, form field, button, badge, status, table shell, pagination, empty state, error state, skeleton, toast, dialog, and drawer patterns.
- Replace emoji/text-glyph navigation icons with one local SVG system.
- Move remaining template inline styles into versioned shared CSS.
- Remove duplicate utility styling and document exceptions.
- Plan removal of Tailwind CDN after equivalent local styles exist; do not remove it while pages still depend on it.
- Create reusable PHP render helpers/partials with narrow contracts instead of copying long markup between routes.
- Create idempotent client behavior modules initialized from `data-ui` hooks for drawer, dialog, disclosure, tabs, filter chips, form state, item rows, bulk selection, pagination enhancement, install guidance, and offline guards.
- Use event delegation and `AbortController`; provide teardown when a behavior owns timers, observers, or requests.
- Centralize icons, translations, shipment statuses, and component state names.

Acceptance:

- New pages require no page-local shell CSS.
- All interactive components show visible hover, focus, active, disabled, loading, and error states.
- Component examples pass keyboard and touch checks.

## Phase 3 — Core operational journeys

Modernize in this order:

1. Login and session-expired state.
2. Dashboard.
3. Create Voucher.
4. Voucher Ledger and Bulk Update.
5. Shipment Stock and status update flows.
6. Voucher View.

Requirements:

- Keep New Sender and New Receiver only.
- Remove every default/example/dummy operational value from voucher creation. Sender, receiver, routing, branch, delivery type, currency, charges, notes, item category, weight, and price must begin blank.
- Do not auto-select the first database result. Every select has a disabled blank instruction and validation before submission.
- An initial item row may be present only as a fully blank row. Do not add `0`, a category, a price, a weight, or a calculated-looking amount until the operator enters real values.
- Preserve voucher calculations, identifiers, database writes, and print output.
- Add unsaved-work protection to long forms.
- Keep totals visible without obscuring fields on mobile.
- Add server pagination to large ledgers instead of relying only on a 500-row cap.
- Make bulk actions role- and scope-safe at both query and update time.
- Provide clear loading, no-results, retry, and offline states.

## Phase 4 — Finance and configuration

Modernize:

- Expenses;
- Other Income;
- Profit & Loss;
- Branches;
- Currencies;
- Delivery Types;
- Item Types;
- User Management;
- Notifications;
- Maintenance and developer diagnostics.

Requirements:

- Keep currency totals separate.
- Confirm destructive actions with record context.
- Explain dependency conflicts before deletion.
- Hide developer-only information from operational roles.
- Eliminate raw logs, SQL errors, server paths, diagnostic tokens, and internal load metrics from normal UI.
- Make every add/create form blank. Edit screens display only persisted record values. Never use sample amount, description, category, currency, branch, maintenance name, date, or status.
- Replace remaining emoji and Unicode control icons on every route with the shared local SVG set.

## Phase 5 — Internationalization

- Replace implicit whole-page text translation on modernized components with explicit stable keys.
- Complete English and Myanmar strings for navigation, fields, buttons, states, validation, dialogs, notifications, accessibility labels, install guidance, and offline behavior.
- Verify Myanmar shaping, wrapping, line-height, and button growth at every target width.
- Persist language without first-paint flicker.
- Do not translate operator-entered data or stored operational values.

## Phase 6 — PWA and platform adaptation

- Add correct raster 192 and 512 px icons plus maskable and Apple touch icons; do not rely only on SVG manifest icons.
- Verify standalone display, theme/status-bar behavior, shortcuts, start URL, update prompt, and service-worker lifecycle.
- Add platform-appropriate install help:
  - iOS Safari: Share → Add to Home Screen;
  - Android/Chromium: native prompt when available;
  - desktop Chromium: install affordance when eligible.
- Respect safe areas and virtual keyboard resizing.
- Keep dynamic/authenticated data network-only and static assets versioned.
- Provide a clear new-version refresh flow without discarding unsaved voucher work.
- Build a native-feeling mobile shell for 425, 375, and 325 px with compact app bar, safe-area bottom navigation, focus-safe drawer/sheets, correct browser/system Back behavior, and virtual-keyboard-aware action placement.
- Test Android predictive/system Back and iOS swipe-back without losing entered data or leaving an invisible overlay active.

## Phase 7 — Loading and performance

- Split global and route-specific assets. Load charts and heavy dependencies only on pages that use them.
- Replace unbounded data rendering with pagination or deliberate virtualization where accessible.
- Debounce search, abort stale requests, deduplicate concurrent GETs, and prevent duplicate submissions.
- Keep Redis fail-open and restricted to suitable lookup/reference data.
- Pause polling when hidden and reduce notification payloads.
- Self-host critical fonts or use a robust system fallback strategy.
- Evaluate removal of Toastify and Chart.js CDN dependencies if local or native alternatives meet requirements.

Targets on a representative mid-range mobile device and normal 4G:

- Largest Contentful Paint under 2.5 s for the shell.
- Cumulative Layout Shift under 0.1.
- Interaction to Next Paint under 200 ms for local UI interactions.
- Initial route JavaScript kept intentionally small; report actual measured size rather than claiming a target was met.

## Phase 8 — Accessibility, security, and resilience

- Complete keyboard-only and screen-reader passes.
- Test 200% text zoom, reduced motion, high contrast, and touch-only input.
- Verify CSRF and direct-route authorization for every POST.
- Verify branch/region scope for submitted record IDs.
- Verify GET routes do not mutate state.
- Verify all user content is escaped and validation errors do not expose internals.
- Test database/Redis/network failure states without presenting false success.
- Scan rendered routes for emoji, fake examples, default business values, silent first-option selections, duplicate IDs, missing labels, and inaccessible icon-only actions.

## Phase 8.5 — Full route convergence

- Re-run the route inventory and mark each active route complete, intentionally retired, or blocked by a documented backend dependency.
- Apply the same shell, tokens, components, icons, blank-form policy, i18n, loading states, error handling, and mobile behavior to every active route.
- Remove superseded markup, styles, handlers, and dead navigation only after the replacement is verified.
- Search the full project for legacy V3 shell fragments, duplicate headers, emoji/icons, sample values, customer UI, portal links, and page-specific copies of shared patterns.
- Re-test earlier routes after every shared component change. A later component refactor must not regress completed screens.
- Continue until no active route remains partially modernized.

## Phase 9 — Release

- Run all static checks and `QA_MATRIX.md` scenarios.
- Compare protected voucher print output with baseline.
- Bump application/static cache versions together.
- Commit a coherent release with a clear message.
- Push and deploy only when explicitly authorized.
- Verify the deployed commit and live static asset versions.
- Smoke-test production read-only routes and avoid test writes against real records.
- Document changed files, behavior, checks, deployment commit, and remaining limitations.

## Route completion checklist

For each route, confirm:

- authorization and role navigation;
- single shell/header;
- English and Myanmar;
- 1440/1024/768/425/375/325 px;
- keyboard and touch;
- loading/empty/error/offline states;
- safe destructive actions;
- no horizontal page overflow;
- no stale/private PWA caching;
- no print regression;
- PHP/JS syntax checks.
- local SVG icons only, with no emoji or text-glyph control icons;
- new/create forms start blank with no dummy/example/default business data;
- shared component and behavior-hook usage rather than duplicated implementations;
- mobile app-like interaction at 425, 375, and 325 px, including keyboard and safe-area behavior.

## Recommended implementation order inside each route

1. Confirm authorization, data source, mutations, and real empty/error cases.
2. Remove sample/default operational values and unsafe GET mutations.
3. Replace route-local shell/navigation with the canonical shell.
4. Rebuild with shared PHP components and `data-ui` behavior hooks.
5. Add explicit English/Myanmar keys and accessible SVG icons.
6. Implement loading, empty, error, offline, and success states.
7. Verify desktop, tablet, 425, 375, and 325 px.
8. Verify keyboard, touch, screen reader, zoom, reduced motion, and platform behavior.
9. Run static checks and inspect console/network output.
10. Fix findings, then re-run the route gate before moving on.

## Explicit non-goals without separate approval

- Database redesign or customer-table deletion.
- Replacing PHP/MySQL with a new framework.
- Changing voucher numbering, calculations, statuses, or print format.
- Adding fake live tracking, fake success, or offline write synchronization.
- Reintroducing public website, CMS, customer portal, or old POS controls.
- Deploying production simply because implementation checks pass.
- Adding framework-only abstractions, React-style hooks, or a client-side router without a separately approved architecture migration.
- Prefilling create forms to make screenshots look complete.
