# Implementation Progress

Status as of this phase. A module is only marked "Done" if it has real
migrations, a real API, real authorization, and (for anything with UI) a
real working frontend — no placeholders, no TODOs, no fake data.

## Scope decision: this is a stock/FIFO inventory tracker, not an ERP

The project went through two phases. Phase 1 built a generic
Sales/Purchases/Customers/Suppliers/Warehouses/Payments/Expenses model
straight from the master prompt's generic template, before any
screenshots had been reviewed. Once the actual reference screenshots
(Ozipco Inventory) were analyzed, it became clear the real application has
**no sales, purchases, customers, suppliers, pricing, or multi-warehouse
concept anywhere** — its sidebar is Dashboard / Daily Activity / Products
/ Inventory / Stock IN / Stock OUT / Categories / Stock Ledger / Reports /
Admin Users / Notifications / Settings, and its core mechanic is FIFO
batch consumption, not transactions with customers or suppliers.

Per explicit instruction, that Phase 1 scope was removed rather than kept
as unused dead code: `Supplier`, `Customer`, `Purchase(Item/Return...)`,
`Sale(Item/Return...)`, `Payment`, `Expense(Category)`, `Brand`, `Unit`
(unit-of-measure), and `Warehouse` — models, migrations, controllers,
and frontend pages — were all deleted, and the schema/permission catalog
was rebuilt around products → batches → movements. The multi-tenant
SaaS layer, Sanctum auth, and RBAC foundation from Phase 1 were kept
unchanged; they don't conflict with either scope.

## Done

| Module | Backend | Frontend | Notes |
|---|---|---|---|
| Multi-tenancy, auth, RBAC | ✅ | ✅ | Unchanged from the foundation phase |
| Categories | ✅ | ✅ | List/create/edit/delete; delete blocked (409) while products are assigned |
| Products | ✅ | ✅ | Active/Archived/Trashed lifecycle, search, CSV export |
| **FIFO batch engine** | ✅ | — | `InventoryService`: stockIn, stockOut (multi-batch FIFO, row-locked), adjust |
| Stock IN | ✅ | ✅ | Creates a batch (boxes × units/box), movement, audit entry |
| Stock OUT | ✅ | ✅ | FIFO-consumes oldest batches first; rejects insufficient stock (422, no partial writes) |
| Stock Adjustments | ✅ | ✅ | Increase (new batch) / decrease (FIFO), reasoned, audited |
| Batch Inventory | ✅ | ✅ | List + status (full/partial/depleted) |
| Inventory Control Center | ✅ | ✅ | Matches the reference screenshots closely: stat cards, rollup table, expandable row with Product Info/Inventory Summary/Movement Stats/FIFO Batches/Recent Movements |
| Stock Ledger | ✅ | ✅ | Full movement history, filterable, CSV export |
| Dashboard | ✅ | ✅ | Period selector (today/yesterday/week/month), opening/net-flow/closing computed from the ledger (correct for any period, not just "now"), inventory health % |
| Daily Activity | ✅ | ✅ | Date-scoped view over the audit trail |
| Audit Log | ✅ | ✅ | Full, unfiltered, read-only |
| Notifications | ✅ | ✅ | Threshold-crossing (low/out of stock) + admin-activity events; read/unread, mark-all-read |
| Admin Users | ✅ | ✅ | List/create/edit, activate/deactivate, reset password, role assignment |
| Roles & Permissions | ✅ (read) | ✅ (read) | 3 fixed templates (Owner/Manager/Staff) per business; no custom-role builder |
| Settings | ✅ | ✅ | General, inventory threshold default, notification toggles |
| Reports | ✅ (thin) | ✅ | Curated filtered views over the endpoints above (see "Reports" below) |

Backend test suite: **22 tests / 102 assertions**, all passing —
including the spec's own 3-batch FIFO example verbatim (Batch A=300,
B=500, C=700 → Stock OUT 600 → A=0, B=200, C=700), multi-batch
consumption, insufficient-stock rejection (writes nothing), adjustment
increase/decrease, product lifecycle transitions, category delete-guard,
tenant isolation, and RBAC enforcement. Run with `php artisan test`.

Frontend: `npm run lint`, `npx tsc --noEmit`, and `npm run build` all
pass with zero errors. The full golden path — register, create category/
product, Stock IN, Stock OUT, view Inventory Control Center with
expandable batch detail, Stock Ledger, Batch list, Stock Adjustment,
Dashboard, Daily Activity, Audit Log, Notifications, Admin Users, Roles,
Settings, Reports navigation, and the full Active→Archived→Trashed→Active
product lifecycle — was driven end-to-end in a real headless browser
against the live dev servers, not just asserted to compile.

## Reports: one set of data, several filtered views

Rather than build 9 separate report endpoints/pages that duplicate the
inventory/ledger/batch/activity data (which the spec explicitly warns
against — "do not create duplicate APIs"), Reports is a landing page of
curated links into the same Inventory, Stock Ledger, Batches, and Daily
Activity views with query-string presets (e.g. "Low Stock" →
`/inventory?status=low_stock`). Each underlying page/endpoint already
supports the filtering, search, sorting, pagination, and CSV export the
spec asks for.

## Deliberately not built this phase

- **Permanent product deletion.** Only Active/Archived/Trashed exist.
  Hard-deleting a product with batch/movement history would either
  cascade-delete audit-relevant rows or orphan them — both bad. Left out
  rather than guess the right safeguard; flagged as
  `UNKNOWN — NEEDS CONFIRMATION` in the original planning pass and never
  resolved, so it stays unbuilt rather than invented.
- **Custom role builder.** Owner/Manager/Staff are fixed per business.
  Read-only `/roles` exists; there's no UI or API to create a role or
  edit a role's permission set.
- **Batch expiry/lot dates.** Not present in any reference screenshot;
  flagged as a recommendation, not built.
- **Password reset via email / email verification.** Sanctum supports
  both; no mail-sending infrastructure is configured in this environment,
  so "reset password" in Admin Users is an admin-sets-a-new-password
  action, not a self-service email flow.
- **Session/password-policy settings** (mentioned in the Settings spec's
  "Security" sub-section) — General/Inventory/Notification settings are
  real; session timeout and password-policy configuration are not
  implemented.

## Known limitations

- `units_per_box` in the Inventory Control Center rollup shows blank
  when a product's active batches disagree on box size (by design — see
  `docs/ARCHITECTURE.md`); the per-batch detail always shows the real
  ratio.
- No automated frontend component/unit tests — only the manual
  Playwright golden-path run described above and the backend feature
  suite.
- True concurrent-process load testing of the FIFO row-locking has not
  been run against MySQL (only logically verified against SQLite in
  tests) — see `docs/ARCHITECTURE.md`.

## Bugs found and fixed during this phase (for the record)

Carried over from the foundation phase (tenant-scope fail-open,
middleware ordering, `apiResource`'s per-action middleware footgun,
Spatie's default guard resolution, a Server/Client Component boundary
violation) — see git history for those. New ones found while building
the inventory engine:

1. **Silent mass-assignment failure on `archived_at`.** `Product`'s
   `#[Fillable]` list didn't include `archived_at`, so
   `$product->update(['archived_at' => now()])` in the archive/restore
   endpoints silently did nothing (Laravel doesn't throw by default for
   a discarded non-fillable attribute). The archive button returned
   `200 OK` and looked like it worked, but the product never actually
   moved tabs. Caught by a Playwright test that checked the *destination*
   tab, not just the response status. Fixed with `forceFill()` for these
   internal, non-user-supplied state transitions.
2. **Ambiguous Playwright selector, not an app bug.** `page.click('button:has-text("Archive")')`
   matched the "Archived" *tab* button (substring match, and it renders
   earlier in the DOM) instead of the row's "Archive" action — a lesson
   in why `getByRole(..., { exact: true })` matters, not a defect in the
   shipped code. Verified by inspecting actual network requests.
3. **Two dead/placeholder code paths caught in self-review before they
   shipped**: an empty `whereRaw` closure for the Inventory `status`
   filter (implemented properly instead) and a route middleware group
   with no real permission behind it (removed).

## Recommended next phase

Per the spec's own phase ordering, everything through "Settings" (Phase
18) is now built. Remaining phases: broader automated frontend testing,
a security-focused review pass, performance profiling under realistic
data volumes, and Hostinger deployment execution (the deployment
*documentation* already exists — see `docs/DEPLOYMENT.md`).
