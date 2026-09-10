# Implementation Progress

Status as of this phase. A module is only marked "Done" if it has real
migrations, a real API, real authorization, and (for anything with UI) a
real working frontend — no placeholders, no TODOs, no fake data.

## Phase: Business Management Expansion (Suppliers + Product Pricing)

Added Suppliers (full lifecycle, never hard-deleted), Supplier↔Product
relationships, supplier tracking on Stock IN, and Product Reference
Pricing with full append-only history — **without modifying the FIFO
engine's quantity logic at all**. The only change inside
`InventoryService` is one new optional parameter on `stockIn()`
(`?int $supplierId = null`) that gets stored on the batch and never
participates in FIFO ordering or quantity math. Every pre-existing test
(27, from before this phase) still passes unmodified.

| Module | Backend | Frontend | Notes |
|---|---|---|---|
| Suppliers | ✅ | ✅ | Active/Inactive/Archived, CSV export, never hard-deleted (`restrictOnDelete` on `stock_batches.supplier_id`) |
| Supplier ↔ Product links | ✅ | ✅ | Inline on the supplier detail page; one `is_primary` per product |
| Stock IN supplier tracking | ✅ | ✅ | Optional `supplier_id` on Stock IN; shown on batches, the Batches list/filter, and the enhanced product detail view |
| Product Reference Pricing | ✅ | ✅ | Single append-only `product_prices` ledger; "current price" always derived live, never cached |
| Product Price Catalog / Report | ✅ | ✅ | Search/category/status/missing-price filter, CSV export |
| Enhanced Product detail view | ✅ | ✅ | Added Supplier Info + Price Info sections to the existing Inventory Control Center row-expand detail |
| Enhanced Supplier detail page | ✅ | ✅ | Supplier Info, Supplied Products (with first/last-supplied dates), Totals, Recent Stock In |
| Supplier / Product-Supplier reports | ✅ | ✅ | `/reports/suppliers`, `/reports/product-suppliers`; "Stock In by Supplier" reuses `/batches?supplier_id=` rather than a new endpoint |
| Dashboard expansion | ✅ | ✅ | Supplier KPIs (total/active/recently-used/top-5-by-quantity), product data-quality alerts (missing supplier / missing price counts + samples) |
| New permissions | ✅ | ✅ | `suppliers.*`, `product_prices.*`, `supplier_reports.*`; Staff gets `suppliers.view` only, never `product_prices.*` |
| New notifications | ✅ | ✅ | `supplier_added` (every time — rare event), `product_missing_supplier` / `product_missing_price` (de-duplicated per product). Deliberately **no** "Stock IN completed" notification — see below |
| Audit logging | ✅ | n/a | `supplier_created/updated/archived/restored`, `supplier_product_linked/updated/unlinked`, `product_price_recorded/note_updated` — all via the existing `AuditLogger` |
| Sidebar restructure | n/a | ✅ | Regrouped into Dashboard / Inventory / Products (+ Product Prices) / Suppliers / Activity / Reports / Administration |
| Upgrade path for existing businesses | ✅ | n/a | `php artisan app:sync-role-permissions` — idempotent, re-syncs Owner/Manager/Staff against the current catalog; required after this deploy (see `docs/DEPLOYMENT.md`) |

### Judgment call: no "Stock IN completed" notification

The expansion spec's example notification list included "stock in
completed." This directly conflicts with the notification system's
own established anti-spam principle from the prior phase (notify on
threshold crossings, not on every movement) — Stock IN happens
constantly in normal operation, and a notification on every one of them
would be exactly the noise that principle exists to prevent. Kept the
existing behavior (no per-transaction Stock IN notification) and added
only the notifications that are genuinely rare/actionable: a new
supplier being added, and a product missing a supplier or price
(each fired once per product, not repeatedly).

### Judgment call: `product_prices` as one table, not two

The spec's example schema mentioned `product_prices` and
`product_price_history` as separate tables. Built as a single
append-only ledger instead — see `docs/ARCHITECTURE.md` → "Product
Pricing" for the reasoning (no cache table means nothing can drift out
of sync with its own history).

### New tests (21, on top of the prior 27)

`tests/Feature/Inventory/StockInSupplierTest.php` (4 — supplier
recorded on batch, supplier optional, supplier never affects FIFO
order/quantity, end-to-end via the API), `tests/Feature/Supplier/SupplierTest.php`
(4 — CRUD/archive/restore, archiving never deletes history, tenant
isolation, Staff-view-only/Manager-can-create RBAC),
`tests/Feature/Supplier/SupplierProductTest.php` (4 — link/unlink,
single-primary enforcement, duplicate-link rejection, tenant isolation),
`tests/Feature/Supplier/SupplierReportTest.php` (3 — aggregation
correctness, missing-supplier flag, RBAC), `tests/Feature/Pricing/ProductPriceTest.php`
(5, including the critical regression test that recording three prices
in a row leaves a batch's `remaining_units`/`status` and the product's
movement count byte-for-byte unchanged), `tests/Feature/Dashboard/DashboardSupplierKpiTest.php`
(1). **Backend test suite: 48 tests / 288 assertions, all passing.**

Frontend: `npm run lint` and `npm run build` (which runs the TypeScript
compiler as part of its build step) both pass with zero errors. A
14-step Playwright run against the live dev servers exercised the full
new golden path end-to-end — register → dashboard KPIs/alerts render →
create product → create supplier → link product to supplier from the
supplier detail page → Stock IN with a supplier selected → the
Inventory Control Center's expanded row shows the linked supplier and
"no price" alert → record a reference price → the Price Catalog shows
it → both new report pages load real data — with **zero browser
console/page errors** observed throughout.

### Bug found and fixed during this phase

**Two model defaults silently broke `Supplier`/`SupplierProduct` creation.**
`Supplier::create(['name' => 'X'])` (no `status` passed) left the
in-memory model's `status` attribute `null` — Eloquent doesn't refetch
a row after `create()`, and `status` relies on a DB-level default, so
the attribute was never populated on the object actually returned to
the controller. `SupplierResource::lifecycleStatus()` then threw a
`TypeError` (declared `: string` return, got `null`) on the very first
`POST /suppliers` in the browser smoke test — caught immediately since
the create endpoint became unusable. Root-caused to the same category
of bug as the earlier `archived_at`/`#[Fillable]` issue from the FIFO
phase (a PHP-level assumption not matching what the DB layer actually
guarantees at the model-instance level), fixed the same general way:
made the default explicit in PHP too, via `protected $attributes = ['status' => 'active']`
on both `Supplier` and `SupplierProduct`, rather than relying on the
column default alone.

**Upgrade-path gap, caught by using the app as a real upgraded
deployment would.** After adding the new permissions to
`PermissionCatalog`, the already-registered "Verify Co" test business
(created earlier in this same session, before the new permissions
existed as `Permission` rows) still had an Owner role missing
`suppliers.*`/`product_prices.*`/`supplier_reports.*` — exactly the
scenario `app:sync-role-permissions` was built for. Running
`php artisan db:seed --class=PermissionSeeder` (to create the new
`Permission` rows) followed by `php artisan app:sync-role-permissions`
(to re-sync the existing business's roles against them) fixed it
immediately, which is itself a live validation that the command does
what it was designed for — this is now documented as a required step
in `docs/DEPLOYMENT.md` for any existing production deployment upgrading
to this version.

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

**Update — Business Management Expansion phase**: `Supplier` was
reintroduced, this time deliberately and narrowly, per an explicit later
request: as a business-management entity (with Stock IN tracking and a
product-linking relationship) and paired with a new Product Reference
Pricing module. Both are layered *around* the untouched FIFO engine, not
merged into it — see the new section below and `docs/ARCHITECTURE.md`
for the exact boundary. `Customer`, `Sale`, `Purchase`, `Payment`, and
`Expense` remain deliberately absent.

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
| First-admin bootstrap | ✅ | n/a (CLI) | `php artisan app:create-admin` — interactive, production-safe; see `docs/DEPLOYMENT.md` |
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
4. **`/auth/login` always returned empty `roles`/`permissions`.** Found
   while verifying the first-admin bootstrap flow end-to-end.
   `/auth/login` is intentionally outside the `tenant` middleware group
   (the user isn't authenticated yet when it runs), so nothing set the
   Spatie "team" context before the response's `UserResource` serialized
   `$user->load('roles')` — it always resolved against team `null`,
   finding nothing. `/auth/register` never showed this because it sets
   tenant context itself, inline, while creating the business. The
   practical effect: every login (not just the new admin's) left the
   frontend with an empty `permissions` array until a full page reload
   — every sidebar item is permission-gated, so a freshly logged-in user
   saw a blank sidebar and no Stock IN/OUT buttons, silently, with no
   error. Fixed on both ends: the backend now sets tenant/team context
   explicitly in `AuthController::login()` before building the response,
   and the frontend's `login()` now uses the `/auth/me` follow-up
   call's user (already correct) instead of discarding it. Covered by
   `tests/Feature/Auth/LoginTest::test_login_response_includes_the_users_roles_and_permissions`.

## Recommended next phase

Per the spec's own phase ordering, everything through "Settings" (Phase
18), plus the full Business Management Expansion (Suppliers + Product
Pricing), is now built. Remaining phases: broader automated frontend
testing, a security-focused review pass, performance profiling under
realistic data volumes, and Hostinger deployment execution (the
deployment *documentation* already exists — see `docs/DEPLOYMENT.md`,
including the new required `app:sync-role-permissions` upgrade step).

Not built, deliberately, in this expansion: a custom supplier-report
export scheduler, multi-currency conversion (currency is stored per
price entry but never converted), and any UI for bulk-importing supplier
product catalogs — none were explicitly requested and would be guessing
at requirements.
