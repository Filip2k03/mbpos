# MBLOGISTICS POS V5 — Product and Interface Design Specification

## Design intent

MBPOS is a logistics workstation, not a marketing site. Its interface should reduce hesitation and mistakes during repetitive shipment work. The visual personality is clean air, reliable transport, and precise financial handling: cool whites, restrained logistics blue, readable navy text, and status colors used only when they carry meaning.

The experience should feel designed by people who understand operators:

- show the next useful action;
- explain empty and error states in plain language;
- keep entered work visible;
- confirm important outcomes with the record identifier;
- never celebrate routine actions with distracting animation;
- never blame the user for a system or connectivity failure.

## Experience principles

1. **Operational clarity:** labels use familiar logistics language and avoid developer terminology.
2. **One place for one action:** navigation and page actions are not duplicated across competing headers.
3. **Fast recognition:** consistent placement, icons, colors, and status vocabulary reduce reading time.
4. **Progressive detail:** show essential facts first; reveal advanced filters and diagnostics on demand.
5. **Honest system state:** loading, offline, stale, failed, empty, and completed states look and read differently.
6. **Forgiving input:** preserve safe user input, validate close to the field, and explain how to recover.
7. **Accessible by default:** keyboard, touch, zoom, Myanmar text, and reduced motion are core requirements.

## Visual foundation

### Color tokens

Use semantic custom properties. Values may be tuned after contrast testing, but meanings must stay stable.

```css
:root {
  --color-canvas: #f4f8fd;
  --color-surface: #ffffff;
  --color-surface-subtle: #f8fbfe;
  --color-text: #142b45;
  --color-text-muted: #61768e;
  --color-border: #dce8f3;
  --color-primary: #0b6ff5;
  --color-primary-strong: #0759cc;
  --color-accent: #12b8ef;
  --color-success: #0b9f73;
  --color-warning: #d68a00;
  --color-danger: #d92d4f;
  --color-info: #1675e8;
  --focus-ring: 0 0 0 3px rgb(11 111 245 / 22%);
}
```

- Do not use gradients on every control. Reserve the blue gradient for the primary action, active navigation, and key brand moments.
- Status color is supplemental. Always include readable status text and, where helpful, an icon or shape.
- Meet WCAG 2.2 AA contrast: 4.5:1 for normal text and 3:1 for large text and meaningful UI graphics.

### Typography

- Prefer a local/system font stack that renders both Latin and Myanmar well: `Inter`, `Noto Sans Myanmar`, system UI, sans-serif.
- Body text: 14–16 px, line-height 1.5–1.65.
- Labels and metadata: minimum 12 px; avoid ultra-small 9–10 px operational text.
- Page title: fluid 24–34 px, weight 750–850.
- Use tabular numerals for money, weights, voucher counts, and timestamps.
- Never uppercase Myanmar text. Uppercase English kickers sparingly.

### Spacing, radius, and elevation

- Base spacing unit: 4 px. Primary rhythm: 8, 12, 16, 20, 24, 32.
- Control height: 44 px minimum; 48 px for primary mobile actions.
- Radius: 10 px controls, 14 px compact cards, 18 px primary panels, full radius for pills.
- Use one subtle panel shadow and one floating-overlay shadow. Borders should carry most separation.
- Glass blur is optional decoration, never required for readability. Provide an opaque fallback and avoid stacking multiple blurred surfaces.

## Canonical application shell

### Desktop: 1280 px and wider

- Expanded sidebar: 248 px, fixed/sticky, independently scrollable when required.
- Top utility bar: 72 px, sticky, aligned to content rather than spanning under the sidebar.
- Main content max width: 1440 px with 24–32 px gutters.
- Sidebar order: brand, grouped navigation, flexible spacer, connectivity/version block.
- Utility bar order: sidebar collapse control, contextual/global search, connection state, language, install when applicable, notifications, account menu.
- Account details live in one menu; logout is not an unexplained icon.

### Compact desktop/tablet landscape: 900–1279 px

- Sidebar collapses to 72 px and exposes accessible labels with tooltips.
- Collapse preference persists locally but never hides the active route indication.
- Search may become an icon-triggered overlay below 1024 px.
- Dense tables remain tables, with sticky first/identity columns only when this materially aids comparison.

### Tablet portrait: 768–899 px

- Default to collapsed rail or off-canvas navigation depending on available width and pointer capability.
- Two-column forms become one or two balanced columns; no 12-column desktop assumptions.
- Page header actions wrap beneath the title in a stable toolbar.
- Tables use contained horizontal scrolling with a visible affordance and sticky header.

### Mobile: 0–767 px

- No persistent sidebar.
- Top bar: menu, compact brand/title, connection indicator, notifications/account.
- Bottom navigation: Dashboard, Create, Shipments, Ledger. The central Create action may be emphasized but must not cover content.
- Full menu drawer contains Finance, Configuration, Administration, language, install help, account, and logout according to role.
- Reserve padding for bottom navigation plus `safe-area-inset-bottom`.
- Page actions may use a sticky bottom action bar only when the page has a single clear primary action. Never stack it on top of the global bottom navigation without reserved space.

## Dynamic sidebar specification

### Behavior

- Group items by task, not by file name.
- Expand only the active group by default; remember explicit user choices during the session.
- Active state must use `aria-current="page"`, color, icon, and background—not color alone.
- Badges show useful counts such as unread notifications or actionable pending work. Do not display decorative or stale counts.
- Tooltips appear for collapsed items on hover and keyboard focus.
- The navigation drawer traps focus while open, closes with Escape, restores focus to the trigger, and prevents background scrolling.
- Clicking the current mobile route closes the drawer without reloading unnecessarily.

### Role-aware content

| Area | Developer/Admin | Staff | Myanmar/Malay operations |
| --- | --- | --- | --- |
| Dashboard | Yes | Yes | Yes |
| Create voucher | Yes | Yes | Yes when server policy allows |
| Shipments and ledger | Yes | Yes, scoped | Yes, region scoped |
| Finance | Yes | Only explicitly permitted routes | Only explicitly permitted routes |
| Configuration | Yes | No by default | No by default |
| Users, maintenance, diagnostics | Role-specific | No | No |

Server-side route authorization remains authoritative. This table guides presentation and must be reconciled with actual policy helpers during implementation.

## Page anatomy

Every modernized route follows this order:

1. Breadcrumb only when the route is deeper than one level.
2. Page header: kicker, single `h1`, plain-language purpose, count/state, primary action.
3. Optional summary strip for high-value numbers.
4. Filters/search in a collapsible panel; active filters appear as removable chips.
5. Main content panel: table, cards, form, or detail view.
6. Pagination or result boundary explanation.
7. Contextual help only when the task is unfamiliar or risky.

Avoid repeating the page title in multiple cards. Do not show internal diagnostics on normal operational dashboards.

## Component patterns

### Buttons

- Primary: one per action area; clear verb and object, e.g. “Create voucher”.
- Secondary: safe supporting action.
- Tertiary/ghost: navigation, reset, cancel.
- Danger: destructive action only; never used for ordinary deactivation unless the consequence is destructive.
- Loading buttons retain width, show progress, set `aria-busy`, and prevent duplicate submission.

### Forms

- Labels stay visible above fields; placeholders are examples, never replacements for labels.
- Required fields use text or a legend, not only a red asterisk.
- Inline help appears before an error; inline errors appear below the field and are linked with `aria-describedby`.
- Group sender, receiver, routing, items, charges, and review by task.
- On mobile, use suitable input modes (`tel`, `decimal`, `numeric`) and autocomplete attributes without forcing incorrect assumptions.
- Voucher create retains New Sender and New Receiver only.

### Tables and lists

- Server pagination is required for large datasets.
- On 425 px and below, choose per route:
  - compact horizontal table for comparison-heavy data; or
  - labeled record cards for action-heavy data.
- Never transform a financial comparison table into cards if that makes totals harder to scan.
- Bulk selection shows selected count and keeps the batch action near the selection context.
- Empty state states what is empty and what the user can do next.

### Status and feedback

- Canonical shipment vocabulary must come from one source; do not invent route-specific aliases.
- Toasts are for short confirmation and non-blocking notices. Validation and persistent failures remain in page context.
- Notification polling must not replay old records on first load.
- Connection status is compact when online and prominent when offline or degraded.

### Dialogs and drawers

- Use for focused confirmation or secondary detail, not full-page forms.
- Provide title, consequence, primary/secondary actions, Escape behavior, initial focus, focus trap, and focus restoration.
- Destructive confirmation names the affected voucher/category/record.

## Screen-specific direction

- **Dashboard:** operational snapshot and shortcuts only. Remove greeting/shift/diagnostic/system-load clutter from the primary experience.
- **Create Voucher:** focused entry flow, immediate totals, protected unsaved work warning, clear review step, and unchanged print output.
- **Shipments:** high-signal filters, scoped results, status timeline access, and efficient bulk actions.
- **Voucher Ledger:** server pagination, stable columns, quick view, export, and role-safe bulk status handling.
- **Voucher View:** readable shipment story, sender/receiver, route, items, charges, payment, status history, and print action.
- **Finance:** currency-safe totals; never combine currencies into one misleading number. Date ranges and export state must be explicit.
- **Configuration:** compact CRUD lists, clear active/inactive meaning, safe deletion rules, and dependency warnings.
- **Maintenance/Diagnostics:** developer-only, calm, and separated from routine operations.
- **Login:** lightweight, install-aware, accessible, and free of internal system language.

## i18n and Myanmar typography

- Allocate 30–45% more horizontal space for translated labels.
- Allow navigation and buttons to grow or wrap intentionally; never truncate critical actions.
- Use fonts with verified Myanmar shaping and line-height around 1.65 for Myanmar body text.
- Translate meaning, not word order. Review terminology with an operator familiar with Myanmar logistics language.
- Dates, numbers, and currency codes remain unambiguous and consistent with stored values.

## Accessibility and input modes

- Visible focus for every interactive element.
- Logical DOM and tab order independent of visual grid placement.
- Skip link to main content.
- Live regions only for concise, important updates.
- Charts require a textual/table equivalent.
- Hover interactions must also work with keyboard and touch.
- Respect browser text scaling up to 200% without losing actions or content.
- Respect reduced motion, forced colors, dark-mode preference only if a complete dark theme exists, and high-contrast environments.

## Protected output

The voucher print artifact is not part of the shell redesign. Do not change its structure, dimensions, fonts, data mapping, background photo, barcode/QR placement, or print media rules as collateral work.

