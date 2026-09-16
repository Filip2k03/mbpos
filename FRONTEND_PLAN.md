# MBLOGISTICS POS V5 — Frontend Build Plan

## Goal

Deliver one production-quality responsive frontend across every active POS route, using a dynamic role-aware sidebar, one shared header, explicit English/Myanmar support, safe PWA behavior, and the existing PHP/MySQL backend contracts.

This plan is sequenced so each phase can ship without breaking operations.

## Phase 0 — Baseline and inventory

- Record the current Git commit and dirty worktree.
- Capture screenshots at 1440, 1024, 768, 425, 375, and 325 px for every routed page.
- Inventory every route in `pos/index.php`, its allowed roles, forms, destructive actions, client dependencies, table size, and current loading/error/empty behavior.
- Identify orphaned routes and UI for customer features, portals, CMS, and old POS surfaces.
- Record the current voucher print at representative data lengths for regression comparison.
- Measure current asset count, transferred bytes, PHP response time, layout shifts, and accessibility issues.

Deliverable: route inventory and before-state evidence attached to the implementation handoff. Do not mutate the database during baseline capture.

## Phase 1 — Canonical shell and dynamic navigation

- Create one PHP navigation registry with role, route aliases, group, icon, mobile priority, and optional badge metadata.
- Render desktop sidebar, tablet rail, mobile drawer, and bottom navigation from the registry.
- Implement sidebar expanded/collapsed state, focus-safe mobile drawer, active route state, tooltips, and scroll behavior.
- Consolidate to one top utility bar and remove legacy V3 inline shell styling after shared styles are complete.
- Remove Customers and legacy portal links from the target shell.
- Add a clear account menu and connection state.
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

## Explicit non-goals without separate approval

- Database redesign or customer-table deletion.
- Replacing PHP/MySQL with a new framework.
- Changing voucher numbering, calculations, statuses, or print format.
- Adding fake live tracking, fake success, or offline write synchronization.
- Reintroducing public website, CMS, customer portal, or old POS controls.
- Deploying production simply because implementation checks pass.

