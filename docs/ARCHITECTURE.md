# Architecture

## What this system is

A multi-tenant **warehouse / stock inventory management SaaS**, modeled on
an existing single-business application ("Ozipco Inventory") whose
screenshots were used as the functional and visual reference. It is
**not** a sales/POS/ERP system: there is no customer, supplier, purchase
order, or pricing layer. The core workflow is:

```
Stock IN → Batch created → Inventory available → Stock OUT → FIFO batch consumption → Stock balance
```

This was a deliberate scope decision, confirmed against the reference
screenshots (no Sales/Purchases/Customers/Suppliers/pricing anywhere in
the existing app's navigation) — see `docs/PROGRESS.md` for the full
decision record. The architecture is intentionally modular so a
sales/purchasing layer could be added later without restructuring the
inventory core.

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

Same team-scoped Spatie Permission setup as the platform foundation, with
the permission catalog now matching the inventory-only module set:
`dashboard`, `products`, `categories`, `inventory`, `batches`, `stock`
(`in`/`out`/`adjust`), `ledger`, `activity`, `audit`, `reports`, `users`,
`roles`, `settings`. See `app/Support/PermissionCatalog.php`. Owner gets
everything; Manager gets everything except user/role/settings management;
Staff gets view + stock in/out only (no adjustments, no deletes).

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
the system does **not** notify on every stock movement. It notifies on
**threshold crossings** (a movement that takes a product's balance from
above its `min_stock_level` to at/below it, or from >0 to 0) and on
admin-noteworthy events (adjustments, archive/trash/restore). See
`App\Services\NotificationService`.

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
                                 stock_adjustments → notifications → business_settings → audit_logs
tests/Feature/
  Auth/                          Registration, login
  Tenancy/                       Cross-tenant isolation
  Catalog/                       RBAC enforcement
  Inventory/                     FifoEngineTest (the spec's own 3-batch example, insufficient-stock
                                   rejection, adjustments), ProductLifecycleTest
```

## Frontend structure

```
src/
  app/(auth)/login|register           Public auth pages
  app/(dashboard)/...                  Authenticated app shell + per-module pages, mirroring the
                                        sidebar structure: Inventory (Overview/Stock IN/Stock OUT/
                                        Ledger/Adjustments/Batches), Product Management (Products/
                                        Categories), Activity (Daily Activity/Audit Log), Reports,
                                        Administration (Notifications/Users/Roles/Settings)
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
