# AGY Execution Prompt — MBLOGISTICS POS V5 Frontend Completion

## Role

You are the senior product engineer responsible for completing the MBLOGISTICS POS V5 frontend in `/Users/stephanfilip/macos2026/payvia/mbpos`. You own implementation quality across all active POS routes, not just the voucher screen.

## Read first

Before editing, read these files completely:

1. `AGENTS.md`
2. `DESIGN.md`
3. `FRONTEND_PLAN.md`
4. `OFFLINE_SYNC_SPEC.md`
5. `QA_MATRIX.md`

Then inspect the dirty worktree and preserve all existing user changes. Audit the router, shared templates, CSS, JavaScript, role helpers, PWA files, and every routed PHP screen before deciding implementation boundaries.

## Objective

Finish a coherent, production-quality V5 interface for `mbpos.online` across desktop, tablet, 425 px, 375 px, and 325 px. Make mobile feel like a deliberate logistics application on iOS and Android, while preserving an efficient desktop workstation experience on Windows and macOS.

Build until every active route satisfies the documentation and QA gates. Do not stop after planning, shell work, or one showcase page.

## Non-negotiable requirements

- One canonical header and role-driven navigation definition.
- Expanded desktop sidebar, compact tablet rail, focus-safe mobile drawer, and no more than four high-frequency mobile bottom actions.
- No Customers entry, customer workflow, public website, website preview, CMS portal, customer portal, old POS, or site-management portal in the target shell.
- Voucher creation uses New Sender and New Receiver only.
- Every new/create form begins blank for business data. Do not insert sample, dummy, guessed, previous, zero, today, first-option, or screenshot-friendly values.
- Voucher sender, receiver, routing, branch, delivery type, currency, charges, notes, item category, weight, price, payment, and status remain blank/unselected until the operator acts, except where an already-confirmed mandatory business rule says otherwise.
- No emoji, Unicode glyph, remote icon, or mixed icon library in the interface. Use one accessible local SVG system.
- Use reusable server-rendered PHP components and idempotent `data-ui` client behavior hooks. Do not add React or a new frontend framework.
- Explicit English/Myanmar keys for all changed UI. Do not translate user or database data.
- Preserve the production database connection, schema, historical data, voucher calculations, numbering, status rules, and authorization boundaries.
- Preserve `voucher_print.php` visual and data output exactly.
- Never cache authenticated/dynamic records in the service worker.
- Implement offline voucher drafts and reconnect synchronization only as specified in `OFFLINE_SYNC_SPEC.md`. Complete payloads belong in versioned, expiring, user-scoped IndexedDB, not `localStorage` or Cache API.
- Never replay the ordinary voucher form POST as an offline queue. Require the dedicated authenticated endpoint, atomic idempotency, current CSRF/session checks, authoritative server validation/calculation, explicit operator queue consent, and recoverable conflict states.
- A local or queued draft is not an operational voucher. Do not generate codes, tracking numbers, statuses, timestamps, ledger entries, or success messages before the server receipt.
- Never fabricate operational data, tracking, status, totals, audit events, cache behavior, success, or errors.
- Do not deploy, delete data/schema, or mutate production records without explicit user authorization.

## Implementation strategy

1. Inventory every active route, role, mutation, current shell fragment, icon, default value, dependency, and responsive failure.
2. Establish semantic tokens and local SVG icons.
3. Create a single navigation registry and reusable PHP shell/components.
4. Create small idempotent JavaScript behavior modules using `data-ui`/`data-action`, event delegation, request cancellation, and explicit state ownership.
5. Complete the shell on all target viewports and platforms.
6. Modernize all operational routes, then finance, configuration, administration, login, offline, and notification states.
7. Remove default/sample data from every create/add form; retain legitimate values only for edit forms, filters, hidden security/protocol fields, and real records.
8. Complete explicit i18n, accessibility, PWA install/update/offline behavior, pagination, loading/error/empty states, and performance work.
9. Implement the staged offline draft/outbox workstream from `OFFLINE_SYNC_SPEC.md`: server idempotency contract first, local drafts second, explicit queue/review UX third, and foreground reconnect synchronization last. Keep it feature-flagged until every offline test passes.
10. Re-audit the whole repository for partially migrated routes, duplicate headers, customer/portal UI, emoji/glyph icons, copied components, fake values, and dead code.
11. Run all checks and device/role/language scenarios, fix findings, and repeat until acceptance passes.

## Mobile quality bar

- Test 425 × 900, 375 × 812, 325 × 700, and landscape 844 × 390.
- No body-level horizontal scrolling, clipped controls, overlapping bars, hidden validation, or desktop layouts squeezed into mobile.
- Minimum 44 × 44 px touch targets and 16 px mobile input text.
- Correct safe areas, dynamic viewport units, virtual keyboard behavior, orientation changes, iOS swipe-back, Android system Back, coarse pointer, and standalone PWA mode.
- Preserve unsaved form work and warn only when meaningful data exists.
- Keep primary actions thumb-reachable without covering fields, totals, messages, or navigation.
- Use accessible sheets/drawers for short secondary tasks and full routes for complex work.

## Real-world feature recommendations

Implement only when backed by real contracts and data:

- server pagination and shareable filters;
- draft recovery and unsaved-work protection;
- explicit offline voucher outbox with atomic idempotent foreground synchronization after the server contract exists;
- scanning into search using available camera/barcode capabilities;
- status history with actor/time when schema support exists;
- scoped alerts and notification noise controls;
- honest connection/retry state;
- install/update guidance;
- desktop density preference;
- keyboard command palette;
- mobile scan/search shortcuts.

If a backend contract is absent, document the dependency and omit the feature. Never simulate completion.

## Verification gates

Run at minimum:

```sh
find pos -path 'pos/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check pos/assets/js/main.js
node --check pos/sw.js
git diff --check
```

Execute `QA_MATRIX.md` across roles, languages, browsers, viewports, online/offline states, and installed PWA modes. Complete every scenario in the offline voucher matrix, including 20-request replay, browser termination, multiple tabs, session expiry, account switching, stale reference data, update migration, storage failure, and transaction rollback. Search for emoji, hard-coded defaults/examples, customer/portal UI, duplicate shell markup, and inaccessible icon controls. Compare voucher print output to the baseline.

## Completion response

Report:

- routes completed and intentionally retired;
- shared components and behavior hooks created;
- mobile/desktop behavior delivered;
- blank-form/default-data audit result;
- icon and i18n audit result;
- security, pagination, cache, and PWA changes;
- exact checks with pass/fail counts;
- viewport/platform/role/language evidence;
- protected voucher-print comparison;
- files changed;
- known limitations or backend dependencies;
- whether anything was pushed or deployed.

Do not claim completion while an active route remains partially migrated, a required check is failing, or production behavior has not been verified.
