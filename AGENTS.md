# MBLOGISTICS POS V5 — Agent Guide

## Mission

Build `mbpos.online` into a focused, dependable logistics POS used by real operators under time pressure. The product must feel calm, fast, human, and consistent on desktop, tablet, and installed mobile PWA surfaces.

Read this file, `DESIGN.md`, `FRONTEND_PLAN.md`, and `QA_MATRIX.md` completely before changing code. Inspect the current worktree and preserve user changes.

`AGY_BUILD_PROMPT.md` is the execution brief for the next implementation agent. Treat its ordered work loop and acceptance gates as mandatory, not aspirational.

## Product scope

- `mbpos.online` is the POS application. Do not reintroduce a website preview, customer portal, CMS portal, old POS switcher, or system/site-management portal into the POS shell.
- Voucher entry uses manual **New Sender** and **New Receiver** fields only. Do not restore existing-customer search/select flows.
- Customer screens and customer navigation are not part of the target V5 interface. Legacy customer tables or columns may still be referenced by old records; hide or retire UI routes safely, but never delete production data or schema without a separately approved migration and verified backup.
- Existing voucher print content, dimensions, field order, background artwork, and print styling are protected. Interface work must not alter `voucher_print.php` output unless the user explicitly requests a print change.
- Preserve the current production database connection and existing operational data.
- New/create forms must begin operationally blank. Do not prefill sender, receiver, phone, address, routing, destination, branch, delivery type, item category, weight, price, charge, discount, notes, currency, payment state, or date with sample, guessed, previous, or convenient values.
- Do not add dummy records, sample people, example addresses, demonstration totals, fake tracking activity, synthetic cache results, or placeholder business data anywhere in production UI.
- Edit/view screens may display real persisted values. Security fields, route fields, record identifiers, and CSRF tokens may use hidden values because they are protocol data rather than operator-entered business data.

## Source of truth

- Router: `pos/index.php`
- Shared application shell: `pos/templates/header.php` and `pos/templates/footer.php`
- Shared styles: `pos/assets/css/style.css`
- Shared client behavior and i18n: `pos/assets/js/main.js`
- Authentication, role, CSRF, and template helpers: `pos/includes/functions.php`
- PWA metadata and offline behavior: `pos/manifest.webmanifest`, `pos/sw.js`, and `pos/offline.html`
- Production configuration: `pos/config.php` — do not replace credentials, connection behavior, or environment assumptions.

## Required working method

1. Audit before editing. Map a requested behavior to the shared shell and reusable components before patching individual pages.
2. Prefer one shared implementation over page-specific duplication. A route should provide content; the shell should provide navigation, header, responsive behavior, language, install, and connectivity controls.
3. Keep every change deployable. Do not leave half-migrated screens or two competing headers/navigation systems.
4. Preserve permissions. Visible navigation and server authorization must both use the existing role helpers; hiding a link is not access control.
5. Use prepared statements for user-influenced database values, escape rendered values with `e()` or `htmlspecialchars`, and include CSRF protection on state-changing requests.
6. Use progressive enhancement. Core forms and links must work without JavaScript; JavaScript may improve speed and feedback but must not create false success.
7. Do not fabricate operational data, totals, statuses, cache hits, delivery events, or success messages.
8. Do not perform schema deletion, data deletion, deployment, or production mutations unless the user explicitly authorizes that exact action.
9. Bump `APP_VERSION` and the service-worker cache name together when deployed static assets change.
10. Run the checks in this guide and visually inspect affected routes before handoff.
11. Continue the audit → implement → verify → fix loop until every in-scope route passes its acceptance criteria. Do not stop after modernizing only the easiest page or after producing a plan.
12. Do not introduce React or another frontend framework merely to obtain “components” or “hooks.” This project remains progressively enhanced PHP unless a separate migration is approved.

## Architecture rules

### Canonical shell

- There must be exactly one application header and one primary navigation system per viewport.
- Desktop uses the persistent sidebar plus top utility bar.
- Tablet uses a compact/collapsible sidebar plus the same top utility bar.
- Mobile uses a compact top bar, an off-canvas full menu, and a maximum of four high-frequency bottom navigation actions.
- `voucher_create.php` may use a focused workspace layout, but it must reuse the same brand, language, account, online/offline, and mobile-navigation primitives. It must not become a second unrelated shell.
- Page files must not add their own global header, global sidebar, or mobile bottom navigation.

### Dynamic navigation

Create one navigation definition in PHP and render desktop, tablet, mobile drawer, and mobile bottom navigation from it. Each item must define:

- stable key;
- translated label key;
- route;
- icon identifier;
- allowed roles or permission callback;
- active-route aliases;
- mobile priority;
- optional badge provider.

Do not duplicate role checks in four separate markup trees. The server decides visibility; JavaScript only controls presentation state.

Target information architecture:

1. Operations: Dashboard, Create Voucher, Voucher Ledger.
2. Finance: Expenses, Other Income, Profit & Loss.
3. Configuration: Branches, Currencies, Delivery Types, Item Types.
4. Administration: Users, Notifications, Maintenance, Diagnostics/Error Logs as permitted.

Exclude Customers and all external/legacy portals from the target sidebar.

### Reusable components and behavior hooks

- Build server-rendered PHP UI primitives for navigation items, page headers, panels, toolbars, fields, status badges, tables, pagination, empty/error states, dialogs, and mobile action bars.
- Use small, named client behavior modules initialized through stable `data-ui` and `data-action` attributes. These are the project’s behavior hooks.
- Prefer event delegation so repeated rows and dynamically inserted item forms do not register unnecessary listeners.
- Each behavior hook must be idempotent: initializing the shell or a component twice must not duplicate handlers, DOM, requests, or announcements.
- Keep state ownership explicit. PHP owns authenticated records and permissions; the URL owns filters and pagination; the browser may own non-sensitive preferences such as language, sidebar state, and density.
- Do not place business rules inside visual components. Totals and validation must use existing authoritative server rules, with client calculations treated only as immediate previews.
- Define component contracts in code comments or a small component reference: accepted data, required markup, empty/loading/error behavior, accessibility behavior, and mobile transformation.

### Responsive behavior

- Design mobile-first and test at 325, 375, and 425 CSS pixels.
- No horizontal page scrolling. Wide operational tables may scroll inside a clearly bounded table region.
- No clipped actions, overlapping fixed bars, or two-line primary buttons caused by undersized grid columns.
- Inputs must remain at least 16 px on iOS to avoid focus zoom.
- Interactive targets must be at least 44 × 44 CSS pixels.
- Respect `env(safe-area-inset-*)` in installed iOS and Android PWAs.
- Never disable user zoom. Remove `maximum-scale=1.0` and `user-scalable=no` during shell modernization.
- At 425, 375, and 325 px, the interface must feel like an intentional mobile application: compact app bar, predictable back/menu behavior, thumb-reachable primary actions, native-feeling sheets/drawers, preserved form progress, and no desktop panel squeezed into a narrow viewport.
- Account for dynamic mobile viewport units (`dvh`/`svh`), virtual keyboards, landscape mode, notches, home indicators, browser toolbars, coarse pointers, and standalone PWA mode.

### Platform behavior

- Prefer feature detection and CSS media queries over user-agent branching.
- Use `display-mode`, `beforeinstallprompt`, `navigator.onLine`, pointer/hover media queries, and safe-area environment values for capability-specific behavior.
- Platform detection may tailor installation help for iOS Safari, Android Chromium, and desktop Chromium, but must not fork core business behavior.
- Windows, macOS, iOS, and Android must receive the same records, permissions, validation, and status transitions.

### Internationalization

- Every visible shell label, empty state, button, field label, validation message, and accessibility label must have a stable English key and Myanmar translation.
- Prefer explicit `data-i18n` and `data-i18n-placeholder` annotations. Do not depend on the legacy whole-document text walker for newly modernized components.
- Keep entered names, addresses, voucher codes, currencies, database values, and user-authored notes untranslated.
- Persist language locally and apply it before first meaningful paint where practical to avoid language flicker.

### PWA and offline behavior

- Cache only versioned static assets and the neutral offline page.
- Never cache authenticated PHP pages, API responses, voucher data, financial records, or personal information.
- Offline UI must clearly say that live records and writes require connectivity.
- Disable or guard state-changing actions while offline; never queue a write unless an explicit conflict-safe synchronization design exists.
- Provide honest install guidance for iOS, Android, and desktop; hide install controls when installation is unavailable or the app is already standalone.

## UI implementation standards

- Follow tokens and components in `DESIGN.md`; do not introduce another color system or arbitrary one-off shadows/radii.
- Remove legacy V3 inline shell CSS from templates after equivalent shared V5 styles exist.
- Avoid runtime dependence on Tailwind CDN for the final production shell. Migrate used utilities to maintained local CSS or a deliberate build artifact before removing the CDN.
- Use semantic HTML: landmarks, one `h1`, ordered heading levels, real buttons for actions, links for navigation, labels tied to controls, and descriptive table headers.
- Icons must come from one consistent local SVG/icon system. Do not mix emoji, text glyphs, remote icons, and unrelated icon libraries in primary navigation.
- Emoji are prohibited in the production interface, including navigation, buttons, cards, empty states, status indicators, notifications, dashboard metrics, maintenance screens, and accessibility labels. Replace them with local SVG icons or text where an icon adds no meaning.
- Motion must be subtle, useful, and disabled under `prefers-reduced-motion: reduce`.
- Loading states use reserved-space skeletons or compact progress indicators. Empty, error, offline, unauthorized, and success states must be distinct.
- Destructive actions require clear confirmation and must identify the affected record.
- Preserve user input after validation errors whenever safe.
- Creation forms render with blank operator data. Do not use a real-looking placeholder as visual decoration. Labels, short instructions, input modes, and validation explain what belongs in a field.
- Select controls begin with a disabled empty instruction such as “Choose currency”; do not silently select the first database option. Checkboxes, radios, statuses, and dates begin unselected unless the underlying business rule makes a default mandatory and that rule is documented and confirmed.

## Performance rules

- Avoid unbounded queries and rendering thousands of rows. Use server pagination for large ledgers; caps are safeguards, not a pagination substitute.
- Keep Redis optional and fail-open. A cache outage must not break core POS work.
- Cache lookup/reference data only when invalidation is defined. Do not cache per-user authorization decisions or mutable financial/voucher responses as shared values.
- Deduplicate concurrent GET requests, abort stale searches, and debounce type-ahead interactions.
- Pause polling while the document is hidden and avoid replaying historic notifications as fresh toasts.
- Do not load Chart.js, Toastify, fonts, or page-specific code on routes that do not use them.
- Target a responsive interaction within 100 ms and visible feedback for network actions within 300 ms.

## Security and data integrity

- Never expose secrets, credentials, diagnostic tokens, raw SQL errors, stack traces, or server paths in the interface.
- Revalidate authorization and record scope on every POST; never trust submitted IDs because they appeared in a filtered table.
- Validate enum values, dates, IDs, currency codes, monetary precision, weights, and maximum batch sizes on the server.
- GET must not mutate state.
- Use POST plus CSRF for deletion, toggles, maintenance controls, and bulk status changes.
- Maintain an audit-friendly record of high-impact operational changes when the existing schema supports it; do not silently invent an audit trail.

## Required verification

Run at minimum:

```sh
find pos -path 'pos/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check pos/assets/js/main.js
node --check pos/sw.js
git diff --check
```

Then execute the route and device checks in `QA_MATRIX.md`. Test with real authorization boundaries but do not create, update, or delete production records merely for visual QA. Use reversible test data only in an approved non-production environment.

## Definition of done

- One coherent shell at every viewport.
- Role-correct dynamic navigation with no dead entries.
- No Customers or legacy portal links in the target V5 shell.
- All in-scope routes use shared V5 tokens and components.
- English/Myanmar switching covers all changed UI.
- Keyboard, touch, screen-reader, reduced-motion, offline, and PWA behavior is verified.
- Existing voucher print output and production data remain unchanged.
- New/create forms contain no prefilled operational values, fake examples, dummy data, or silent first-option selections.
- No emoji or text-glyph icons remain in the production interface; the approved local SVG system is used consistently.
- Shared PHP components and idempotent client behavior hooks power repeated patterns instead of copied markup and handlers.
- PHP/JS checks pass, the worktree contains only intended changes, and the handoff lists exact tests and known limitations.
