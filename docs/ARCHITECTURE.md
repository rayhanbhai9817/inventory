# Architecture

## What this system is

A multi-tenant **warehouse / stock inventory management SaaS** ("FAST
SOLD LLC" / Ozipco Inventory), whose core workflow is:

```
Stock IN → Batch created → Inventory available → Stock OUT → FIFO batch consumption → Stock balance
```

It is still **not** a sales/POS/accounting system — there is no
customer, purchase order, or sales/invoicing layer. It now does have
**Suppliers** and **product reference pricing**, added in the "Business
Management Expansion" phase, as pure business-management data layered
*around* the inventory engine, never inside it. See "Suppliers" and
"Product Pricing" below for the precise boundary. The original
scope decision (see `docs/PROGRESS.md`) still holds for everything
outside that expansion: no sales, purchase orders, payments, expenses,
or multi-warehouse concept.

## Overview

```
Browser
  │
  ▼
Next.js frontend (App Router, TypeScript, Tailwind)
  │  HTTP/JSON, Bearer token
  ▼
Laravel REST API (/api/v1/*)
  │
  ▼
MySQL (production) / SQLite (local dev)
```

The frontend never queries the database directly and never performs
authoritative inventory calculations — FIFO batch consumption, stock
balances, and permission checks are all computed and enforced
server-side, inside database transactions.

## Multi-tenancy

Unchanged from the platform's foundation: single database, shared
schema, row-level isolation via `App\Models\Concerns\BelongsToTenant`
(fails closed with no tenant context) and
`App\Http\Middleware\EnsureTenantContext`. See the trait/middleware
source for the full mechanism; it applies to every tenant-owned table
below (products, categories, stock_batches, stock_movements,
stock_adjustments, notifications, audit_logs partially — see below).

## The inventory engine

This is the core of the system, implemented in
`app/Services/InventoryService.php`. Three operations, each wrapped in
a `DB::transaction()`:

- **`stockIn`** — creates a new `stock_batches` row (its own
  `boxes` × `units_per_box` = `total_units`, since different deliveries
  of the same product can be packed differently) and a `stock_movements`
  row of type `stock_in`.
- **`stockOut`** — locks every non-depleted batch for the product
  (`lockForUpdate`, ordered oldest `received_at` first, `id` as
  tiebreaker), verifies total available ≥ requested (throwing
  `InsufficientStockException` — never allowing negative inventory —
  before writing anything if not), then consumes oldest-first across as
  many batches as needed. One `stock_movements` row is written, linked
  to every batch it touched via the `stock_movement_batches` pivot, so
  every movement is traceable back to specific batches.
- **`adjust`** — increase creates a synthetic batch (same as a manual
  stock-in); decrease reuses the same FIFO consumption path as
  `stockOut`. Every adjustment is recorded in `stock_adjustments`
  (direction, quantity, reason, note) linked 1:1 to the `stock_movements`
  row it produced.

Concurrency: on MySQL (the production target) `lockForUpdate` takes real
row-level locks, so two simultaneous Stock OUT requests for the same
product cannot both read the same "available" total and jointly
over-consume it. SQLite (used for local dev/tests) serializes writes at
a coarser grain — the *logic* is verified by
`tests/Feature/Inventory/FifoEngineTest.php`, but genuine concurrent-load
testing should happen against MySQL.

Batch codes are short random unique strings (e.g. `#1CD05DAB`, matching
the reference UI). Movement references (`SI-000042`, `SO-000042`,
`ADJ-000042`) are derived from the movement's own primary key — never a
separately tracked counter — so they can't collide or drift.

## Suppliers

`Supplier` is a first-class, tenant-scoped entity (`app/Models/Supplier.php`)
with its own Active/Inactive/**Archived** lifecycle — archive, never
hard-delete: `restrictOnDelete()` on `stock_batches.supplier_id` makes the
database itself refuse to lose a supplier that historical batches still
reference, matching the explicit rule "do not permanently delete a
supplier if historical inventory records depend on it."

Two distinct relationships exist, and the distinction is deliberate —
they answer different questions and are populated independently:

1. **`stock_batches.supplier_id`** (nullable FK) — *which supplier did
   this specific delivery come from?* Set once, at Stock IN time, and
   never changed afterward (a batch is an immutable historical record).
   This is how "Stock In by Supplier" reporting works
   (`/batches?supplier_id=`, `/reports/stock-in-by-supplier`).
2. **`supplier_products` pivot** (`Supplier belongsToMany Product`) — *is
   this product formally associated with this supplier as a source?*
   Managed explicitly (link/unlink, one `is_primary` per product,
   enforced in `SupplierProductController` inside a transaction rather
   than a DB constraint — the same application-level-invariant style
   used elsewhere in this codebase). A product can have Stock IN history
   from a supplier it has no formal `supplier_products` link to yet
   (someone received a one-off delivery); the two facts are tracked
   independently rather than one being derived from the other.

Stock IN accepting an optional `supplier_id` (`InventoryService::stockIn()`'s
last parameter) is the **only** change made to the FIFO engine for this
whole expansion — it is stored on the batch and never participates in
FIFO ordering, quantity math, or any lock/transaction logic. Every
existing FIFO test still passes unmodified, and
`tests/Feature/Inventory/StockInSupplierTest.php` asserts explicitly that
batches with and without a supplier consume in received_at order exactly
as before.

## Product Pricing (reference data — not inventory valuation)

`ProductPrice` (`app/Models/ProductPrice.php`) is a deliberately
**single, append-only ledger table** — no separate "current price" cache
table. "Current price" is always the latest row by `effective_date`
(`Product::currentPrice()`), computed live on read, exactly mirroring
the existing "don't cache, compute live" pattern already used for
`Product::totalRemainingUnits()`. The spec's example schema mentioned
`product_prices` *and* `product_price_history` as two tables; a single
append-only table was chosen instead so there is no cache to go stale —
one write path, one read path, no synchronization to get wrong.

**Recording a new price never overwrites or deletes a prior entry.**
`ProductPriceController::store()` always inserts a new row; `update()`
exists only to correct the `notes` field on an existing entry — `price`
and `effective_date` are immutable once recorded, by design.

This is the hard boundary the spec calls out repeatedly, and it holds
structurally, not just by convention:

- `ProductPrice` has no relationship to `StockBatch`, `StockMovement`,
  or `StockAdjustment`, and nothing in `InventoryService` reads from or
  writes to `product_prices`.
- Recording, changing, or deleting a price cannot change
  `remaining_units`, batch `status`, the stock ledger, Inventory Health,
  or any Stock IN/OUT quantity — there is no code path between them.
- `tests/Feature/Pricing/ProductPriceTest::test_price_changes_never_affect_inventory_quantity_or_fifo_state`
  proves this directly: it stock-ins a product, records three different
  prices in immediate succession, and asserts the batch's
  `remaining_units`, `status`, and the product's movement count are
  byte-for-byte unchanged.

Price is explicitly **not** inventory valuation, purchase cost, sales
price, or profit/margin data — it is reference information an admin can
attach to a product and look back through, nothing more. Staff never get
`product_prices.*` permissions (see "Roles & permissions" below); only
Owner/Manager can view or record prices.

## Product lifecycle

Products have three states, matching the reference UI's Active/Archived/
Trashed tabs — deliberately **not** a single status enum:

- **Active**: `archived_at IS NULL AND deleted_at IS NULL`
- **Archived**: `archived_at IS NOT NULL` (a deliberate, reversible hide)
- **Trashed**: `deleted_at IS NOT NULL` (Eloquent SoftDeletes)

There is no "permanently delete" action in this phase — it was
deliberately left out rather than risk destroying batch/movement audit
history; see `docs/PROGRESS.md`.

## Roles & permissions

Same team-scoped Spatie Permission setup as the platform foundation. The
catalog now also includes `suppliers` (`view`/`create`/`update`/`archive`),
`product_prices` (`view`/`create`/`update`/`history`), and
`supplier_reports` (`view`/`export`) — see
`app/Support/PermissionCatalog.php`. Owner gets everything; Manager gets
everything except user/role/settings management (including the new
supplier/pricing modules in full); Staff gets view + stock in/out only,
plus `suppliers.view` (Staff needs to *pick* a supplier while doing a
Stock IN) but **no** `product_prices.*` at all — pricing stays
admin-only, by design.

### Upgrading an already-provisioned business

`TenantProvisioningService::provision()` only runs once, at business
creation — a business that registered before this expansion has an Owner
role whose permissions were `syncPermissions()`'d against the catalog
*as it existed at signup time*. Adding new permissions to the catalog
later does not retroactively reach it. `php artisan app:sync-role-permissions`
exists specifically to close that gap: it's an idempotent console command
that re-syncs every business's Owner/Manager/Staff roles against the
*current* `PermissionCatalog::defaultRoles()`, safe to re-run after every
deploy that changes the permission catalog. See `docs/DEPLOYMENT.md`.

## Audit trail vs. Daily Activity

`AuditLog` is intentionally **not** tenant-scoped via the `BelongsToTenant`
trait: audit events can legitimately be written without an active tenant
context, so `business_id` is set explicitly by `App\Services\AuditLogger`
rather than auto-stamped, and every read-side query filters by
`business_id` manually. `ActivityController` ("Daily Activity") and
`AuditLogController` ("Audit Log") are two views over the same table —
the former date-scoped and operator-friendly, the latter the full,
unfiltered record. There is no update/delete route for audit logs
anywhere in the API.

## Notifications

Deliberately narrow, per the "avoid excessive notifications" principle:
the system does **not** notify on every stock movement — and, per the
same principle, it deliberately does **not** notify on every Stock IN
either, despite the expansion spec listing "stock in completed" as an
example event. It notifies on **threshold crossings** (a movement that
takes a product's balance from above its `min_stock_level` to at/below
it, or from >0 to 0), admin-noteworthy events (adjustments,
archive/trash/restore), and — new in this phase — genuinely rare,
actionable business events: a new supplier being added
(`supplierAdded()`, fires every time, since a new supplier is rare) and
a product missing a supplier/price (`productMissingSupplier()` /
`productMissingPrice()`, each de-duplicated per product so it fires
once, not on every dashboard load). See `App\Services\NotificationService`.
The `notifications.type` column was widened from a fixed DB `enum` to a
plain string (migration `2026_09_10_090022`) so future notification
types don't each need their own schema migration.

## Backend structure

```
app/
  Http/Controllers/Api/V1/   Thin controllers (validate → service → resource)
  Http/Requests/              Form Request validation, tenant-scoped uniqueness rules
  Http/Resources/              API response shaping
  Http/Middleware/             EnsureTenantContext
  Models/                       Eloquent models
  Models/Concerns/              BelongsToTenant trait
  Services/                     InventoryService (the FIFO engine), AuditLogger, NotificationService,
                                 TenantProvisioningService
  Support/                      Tenant (per-request state), PermissionCatalog, RouteHelpers
  Exceptions/                   InsufficientStockException
database/migrations/           businesses → users → permissions → categories → products →
                                 stock_batches → stock_movements → stock_movement_batches →
                                 stock_adjustments → notifications → business_settings →
                                 suppliers → supplier_products → product_prices →
                                 stock_batches.supplier_id → audit_logs →
                                 notifications.type widened to string
tests/Feature/
  Auth/                          Registration, login
  Tenancy/                       Cross-tenant isolation
  Catalog/                       RBAC enforcement
  Inventory/                     FifoEngineTest (the spec's own 3-batch example, insufficient-stock
                                   rejection, adjustments), ProductLifecycleTest,
                                   StockInSupplierTest (supplier tracking, FIFO unaffected)
  Supplier/                      SupplierTest, SupplierProductTest, SupplierReportTest
  Pricing/                       ProductPriceTest (incl. the price/inventory separation regression test)
  Dashboard/                     DashboardSupplierKpiTest
```

## Frontend structure

```
src/
  app/(auth)/login|register           Public auth pages
  app/(dashboard)/...                  Authenticated app shell + per-module pages, mirroring the
                                        sidebar structure: Inventory (Overview/Stock IN/Stock OUT/
                                        Ledger/Adjustments/Batches), Products (Products/Categories/
                                        Product Prices), Suppliers, Activity (Daily Activity/Audit
                                        Log), Reports, Administration (Notifications/Users/Roles/
                                        Settings). suppliers/, suppliers/[id]/ (detail + inline
                                        supplier-product linking), suppliers/[id]/edit/,
                                        product-prices/ (catalog), product-prices/[id]/ (history +
                                        record price), reports/suppliers/, reports/product-suppliers/
  components/ui/                       Button, Input/Select, Card, Badge (+ StockStatusBadge,
                                        BatchStatusBadge)
  components/layout/                   Sidebar (grouped, permission-aware), Topbar (notification bell)
  components/entity/                    Generic EntityListPage/EntityForm/EntityEditPage for the
                                        simple CRUD modules (categories); AuditLogTable shared by
                                        Daily Activity and Audit Log
  lib/api.ts                           Typed fetch wrapper (bearer token, ApiError)
  lib/auth-context.tsx                 Session state, permission checks
  lib/format.ts                        Shared date formatting
  types/                                TypeScript types mirroring the API resources
```

## Known Next.js 16 gotcha (found via testing, not guessed)

A Server Component page cannot pass a function prop to a Client
Component — this broke six of the original entity edit pages (they
passed a `toValues` callback into a client-side form component) and was
only caught by driving the app in an actual browser, not by `next build`.
Fixed by making the edit pages Client Components that unwrap the async
route `params` with React's `use()` hook. See `docs/PROGRESS.md` for the
full list of bugs caught this way.
