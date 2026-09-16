# MBLOGISTICS POS V5 — Offline Draft and Sync Specification

## Purpose

Allow an operator to continue preparing a voucher during a temporary connection loss and safely submit that work after connectivity returns. Offline behavior must reduce lost work without creating duplicate vouchers, bypassing authorization, weakening server validation, or presenting a local draft as a completed operational record.

This document is the required contract for any offline write implementation. Do not build a client-only queue and assume ordinary form submission is safe to replay.

## Product promises

- An operator can save an unfinished voucher locally while offline.
- A locally saved voucher is always labelled **Local draft**, never Created, Pending, Synced, or assigned a real voucher/tracking number.
- The operator explicitly chooses **Create when online** before a draft enters the outbox.
- A queued draft may synchronize automatically after that explicit choice when the app is open, the session remains valid, and the server confirms the request safely.
- Retrying the same queued request cannot create a second voucher.
- Server permissions, reference data, calculations, validation, numbering, and transaction rules remain authoritative.
- A conflict or expired session stops synchronization and asks the operator to review or sign in. It never fabricates success.

## Non-goals

- Do not make ledger, finance, customer, notification, or historical voucher data available offline.
- Do not cache authenticated PHP pages or API responses.
- Do not edit, cancel, delete, bulk-update, or change shipment status offline.
- Do not assign a voucher code, tracking number, status, timestamp, or ledger result on the client.
- Do not run an unreviewed queue after another user signs into the same browser profile.
- Do not introduce a general-purpose synchronization framework before the voucher-create flow passes this specification.

## Storage model

### `localStorage`

Use `localStorage` only for small, non-sensitive preferences and pointers:

- language and presentation preferences;
- whether the operator dismissed offline guidance;
- the current user-scoped draft identifier, never the voucher payload;
- an outbox-count hint that may be recomputed from IndexedDB;
- schema/version markers needed to migrate or invalidate local storage.

Never store sender names, receiver names, phone numbers, addresses, notes, item details, amounts, CSRF tokens, session values, authorization data, or complete voucher JSON in `localStorage`.

### IndexedDB

Store local voucher drafts and queued payloads in a versioned IndexedDB database because it supports structured records, transactions, indexes, controlled migration, and larger payloads. Suggested database name: `mbpos-v5-offline`; suggested stores:

- `drafts`: editable operator work;
- `outbox`: immutable submission snapshots waiting for the server;
- `receipts`: minimal idempotency receipts retained briefly after success;
- `meta`: schema version and last cleanup timestamp.

Each record must contain:

- random local UUID;
- authenticated user ID and role/branch scope fingerprint;
- created and updated timestamps;
- local schema version;
- draft revision number;
- explicit state;
- expiry timestamp;
- payload or payload reference;
- idempotency key only after the operator queues creation;
- last synchronization attempt and safe error code, without raw server errors.

Full local drafts should expire after 24 hours by default. The final retention period must be confirmed with operations. Successful receipts may remain for up to seven days to prevent accidental replay, but must not retain personal payload data.

Browser-side encryption may be added as defense in depth, but it must not be described as protection from same-origin script compromise. The primary controls are data minimization, short retention, strict Content Security Policy, output escaping, session binding, and deletion on logout or user change.

## State machine

Use these stable states:

| State | Meaning | Allowed next states |
| --- | --- | --- |
| `local_draft` | Saved locally and still editable | `queued`, `discarded`, `expired` |
| `queued` | Operator explicitly approved creation when online | `syncing`, `needs_review`, `discarded`, `expired` |
| `syncing` | One foreground request is active | `synced`, `queued`, `needs_review`, `rejected` |
| `needs_review` | Session, permission, reference data, or calculation changed | `local_draft`, `queued`, `discarded` |
| `rejected` | Server rejected invalid business data | `local_draft`, `discarded` |
| `synced` | Server returned the authoritative voucher receipt | terminal after receipt retention |
| `discarded` | Operator confirmed local deletion | terminal |
| `expired` | Retention limit passed | terminal after clear notice |

State transitions must be transactional. Only one sync worker may own a queued record at a time. A stale `syncing` lease returns to `queued` after a bounded timeout.

## Draft lifecycle

1. The form begins blank and online-first.
2. Meaningful input starts an in-memory dirty state.
3. Debounced draft persistence writes to IndexedDB after input settles and immediately on visibility change, page hide, or an explicit **Save local draft** action.
4. The UI shows the last local save time and makes clear that no voucher exists yet.
5. **Create when online** validates the currently available client fields, snapshots an immutable outbox payload, generates an idempotency key, and records the operator’s explicit intent.
6. When online and the app is visible, the foreground sync coordinator acquires the record and calls the dedicated server endpoint.
7. The server reauthenticates the session, validates CSRF and scope, revalidates all values, calculates authoritative totals, and creates the voucher in one database transaction.
8. The server stores or resolves the idempotency key atomically with voucher creation.
9. The client marks `synced` only after receiving a valid server receipt containing the authoritative voucher ID, voucher code, tracking number, totals, and creation time.
10. The payload is removed, the minimal receipt is retained temporarily, and the operator receives a clear link to the real voucher.

## Required server contract

Do not ship automatic synchronization until a dedicated authenticated endpoint exists. Replaying the existing HTML form POST is not sufficient.

Suggested endpoint:

```text
POST index.php?page=api_voucher_sync
Content-Type: application/json
X-CSRF-Token: <current session token>
Idempotency-Key: <random UUID>
```

Required request fields:

| Field | Type and source |
| --- | --- |
| `clientDraftId` | Client-generated UUID for the local draft |
| `draftRevision` | Positive integer incremented after each local update |
| `schemaVersion` | Positive integer identifying the local payload contract |
| `referenceVersion` | Opaque version previously issued by the server |
| `payloadHash` | SHA-256 digest bound to the idempotency key |
| `voucher.senderName` | Non-empty operator-entered string |
| `voucher.senderPhone` | Non-empty operator-entered string |
| `voucher.receiverName` | Non-empty operator-entered string |
| `voucher.receiverPhone` | Non-empty operator-entered string |
| `voucher.receiverAddress` | Non-empty operator-entered string |
| `voucher.destinationRegionId` | Positive server-issued identifier |
| `voucher.destinationBranchId` | Positive server-issued identifier |
| `voucher.deliveryType` | Current database-backed enum value |
| `voucher.currency` | Current database-backed currency code |
| `voucher.deliveryCharge` | Validated decimal string or empty when optional |
| `voucher.notes` | Operator-entered string or empty when optional |
| `voucher.items` | Non-empty bounded array of database-backed item type, weight, and price values |

This is a contract definition, not a source of form defaults. Never copy documentation values into a new voucher.

Required responses:

- `201 created`: new voucher receipt;
- `200 replayed`: same idempotency key already succeeded; return the same receipt;
- `401 session_expired`: pause the complete outbox and request sign-in;
- `403 forbidden`: mark the record `needs_review`; never retry automatically;
- `409 reference_conflict`: configuration changed; return safe field-level reasons and the current reference version;
- `409 payload_conflict`: the same key was reused with a different payload hash; block and report;
- `422 validation_failed`: return translated field keys and safe validation codes;
- `429` or `5xx`: return to `queued` with bounded exponential backoff and jitter.

The idempotency table or equivalent server mechanism must enforce a unique key scoped to the authenticated user and operation. Voucher creation and idempotency receipt creation must commit atomically.

## Conflict rules

- Reference lists used offline are display aids, not authority. The server must reject inactive/deleted branches, currencies, item types, or delivery types.
- The server recalculates weight and totals. If the result differs from the queued preview beyond the existing precision rules, require operator review before creation unless operations explicitly approves server-normalized submission.
- A changed user role, branch, region, or account invalidates automatic synchronization.
- Drafts from one user are never shown or synchronized for another user.
- Duplicate-looking sender/receiver/item combinations may produce a warning, but the system must not infer identity or silently merge records.
- Queue order is oldest first, but one blocked record must not hide later records. Show each outcome clearly.

## Sync coordinator

Implement a small idempotent behavior module, for example `data-ui="offline-voucher-sync"`.

- Run only while the app is open and visible.
- Use `navigator.onLine` as a hint, then confirm with the real endpoint.
- Permit one active sync request per browser profile.
- Abort when the document becomes hidden, the session expires, the user changes, or logout begins.
- Use bounded backoff; do not hammer the server after reconnect.
- Do not depend on service-worker Background Sync for the first release. Browser support, session expiry, CSRF rotation, and silent high-impact writes make foreground synchronization safer.
- Do not display “Synced” until the authoritative receipt is persisted locally.

## Service worker boundaries

- Continue caching only versioned static assets and `offline.html`.
- Never place drafts, outbox payloads, receipts, authenticated pages, API responses, or voucher records in Cache API.
- The service worker may announce connectivity/update availability but must not create vouchers in the first release.
- An application update must migrate compatible drafts or mark them `needs_review`; it must never drop them silently.
- Bump `APP_VERSION`, service-worker cache name, IndexedDB schema version when applicable, and storage migration tests together.

## Human interface

Required surfaces:

- compact connection banner: **Offline — work is saved on this device**;
- draft state: **Saved locally at 14:32 — no voucher has been created**;
- queue action: **Create when online** with a concise explanation;
- outbox count accessible from Create Voucher and the account/connection area;
- sync state with reserved layout space, not repeated toast spam;
- review screen showing exactly which draft needs attention and why;
- successful receipt with the real voucher code and **View voucher** action;
- discard confirmation naming the local draft and explaining that it cannot be recovered;
- shared-device warning and **Clear local drafts** control after reauthentication.

All text, validation, accessibility labels, timestamps, plural forms, and recovery instructions require human-written English and Myanmar translations. Never translate names, addresses, codes, currencies, database values, or notes.

## Security requirements

- Require HTTPS, same-origin requests, current authenticated session, CSRF protection, and server-side authorization.
- Revalidate every submitted ID and enum against the current user scope.
- Apply existing length, monetary precision, weight, item-count, and batch limits on both client and server.
- Escape all restored values when rendering and assign form values through DOM properties, not HTML string concatenation.
- Clear local payloads on logout, account switch, explicit discard, expiry, or unrecoverable schema migration.
- Never log personal draft payloads, CSRF tokens, session identifiers, or raw server errors.
- Telemetry may record counts, durations, safe result codes, and age buckets only.
- Provide a visible privacy explanation because drafts contain sender and receiver information on the device.

## MoSCoW delivery plan

### Must

- IndexedDB draft store with versioned migrations and expiry.
- User-scoped drafts and logout/account-switch cleanup.
- Explicit queue consent and clear local/queued/syncing/review/synced states.
- Dedicated authenticated sync endpoint with atomic idempotency.
- Server authorization, validation, reference checks, and authoritative calculations.
- Foreground single-worker synchronization with bounded retry.
- English/Myanmar UI and accessible status announcements.
- Full offline/reconnect/duplicate/session/conflict QA before production enablement.

### Should

- Draft list/outbox review screen.
- Field-level conflict recovery that preserves valid input.
- Storage quota checks and actionable failure messages.
- Update migration that preserves compatible drafts.
- Safe operational telemetry and support diagnostics without personal data.

### Could

- Manual encrypted export/import for supervised recovery.
- Web Locks API coordination with a local lease fallback for multiple tabs.
- Background Sync after a separate security and browser-support review.

### Will not in the first release

- Offline edits to existing vouchers or finance records.
- Silent background creation without explicit queue consent.
- Shared-device cross-user drafts.
- Client-generated voucher/tracking numbers or success states.
- Cache API storage of private records.

## Rollout

1. Build the server idempotency contract and automated tests behind a disabled feature flag.
2. Build local draft persistence without submission; test expiry, logout, storage failure, and migrations.
3. Add explicit queueing and a manual **Sync now** control in staging.
4. Run the complete matrix in `QA_MATRIX.md`, including duplicate request replay and server transaction rollback.
5. Pilot with a small approved operator group and collect only non-personal reliability metrics.
6. Enable automatic foreground reconnect sync for explicitly queued drafts after pilot acceptance.
7. Keep a kill switch that disables new queueing while preserving access to existing local drafts for recovery.

## Definition of done

- Replaying a request 20 times creates exactly one voucher.
- Killing the browser during every state transition cannot create an untracked duplicate or lose a recoverable draft.
- Logout and account switching cannot expose or submit another user’s draft.
- Expired sessions, changed permissions, stale reference data, validation failures, and server errors have distinct recoverable states.
- No private content appears in Cache API, `localStorage`, logs, URLs, notifications, or telemetry.
- Online voucher creation continues to work without IndexedDB or JavaScript.
- Existing database connection behavior and `voucher_print.php` remain unchanged.
- English/Myanmar, keyboard, screen-reader, reduced-motion, mobile, desktop, browser, and installed-PWA QA pass.
- The feature remains disabled in production until the server contract, migration/rollback plan, backup, and authorized deployment are complete.
