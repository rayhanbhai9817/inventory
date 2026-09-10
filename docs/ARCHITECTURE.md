# Architecture

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

The frontend and backend are independent applications with a clean HTTP
boundary. The frontend never queries the database directly and never
performs authoritative business calculations — stock levels, pricing,
totals, and permission checks are all computed and enforced server-side.
Frontend-side numbers (e.g. a dashboard "stock value" estimate) are
presentation only and are clearly labeled as such where they are not
exhaustive.

## Multi-tenancy

Tenancy model: **single database, shared schema, row-level isolation.**

- Every tenant-owned table carries a `business_id` foreign key
  (`app/Models/Concerns/BelongsToTenant.php`).
- `BelongsToTenant` is an Eloquent trait that:
  1. Registers a global scope filtering every query to
     `business_id = current tenant`.
  2. **Fails closed**: if no tenant context has been resolved, the scope
     adds an impossible `1 = 0` condition rather than returning
     unscoped (all-tenants) data. This was caught by a test during
     development — see `tests/Feature/Tenancy/TenantIsolationTest.php`.
  3. Auto-stamps `business_id` on record creation from the current
     tenant context.
- `App\Support\Tenant` is a static per-request holder for the current
  tenant id.
- `App\Http\Middleware\EnsureTenantContext` resolves the tenant from the
  authenticated user (`$user->business_id`) and sets both `Tenant` and
  Spatie Permission's "team" id (see Roles & Permissions below) at the
  start of every authenticated request. It is explicitly placed in the
  middleware priority list to run before Laravel's `SubstituteBindings`
  middleware — otherwise route-model binding (e.g. `Product $product` in
  a controller signature) could resolve using a stale or absent tenant
  context.

Line items (e.g. `purchase_items`, `sale_items`) do not carry their own
`business_id` — they inherit isolation through their parent
(`purchase_id` / `sale_id`), which is itself tenant-scoped.

## Authentication

Laravel Sanctum, **token-based** (not cookie/SPA-session based): the
frontend and backend are separate origins, so login/register return a
plain bearer token stored client-side (`localStorage`) and sent as
`Authorization: Bearer <token>`. This avoids CORS/CSRF/stateful-domain
complexity that cookie-based Sanctum SPA auth would require.

## Roles & permissions

`spatie/laravel-permission`, with **teams enabled** and the business id
used as the team id. This means:

- `permissions` are global reference data (e.g. `products.delete`),
  seeded once (`database/seeders/PermissionSeeder.php`,
  `app/Support/PermissionCatalog.php`).
- `roles` (Owner / Manager / Staff) are created **per business** when it
  registers (`app/Services/TenantProvisioningService.php`), so each
  tenant has its own independent set of role→permission assignments,
  even though role *names* repeat across tenants.
- Every permission-gated route pins the guard explicitly
  (`permission:products.delete,sanctum`) rather than relying on Laravel's
  default guard resolution — see the note in `routes/api.php` /
  `app/Support/RouteHelpers.php` for why.

Frontend permission checks (`useAuth().can(...)`, used to show/hide
buttons) are **UX only**. The API is the authoritative enforcement point.

## Inventory model

- `product_stocks`: current quantity per (product, warehouse) — the
  live, authoritative stock number.
- `stock_movements`: an append-only ledger of every quantity change
  (`opening`, `purchase`, `purchase_return`, `sale`, `sales_return`,
  `adjustment_increase`, `adjustment_decrease`, `transfer_in`,
  `transfer_out`), each recording quantity_before/after and an optional
  polymorphic reference to the transaction that caused it.

As of this phase, `product_stocks` rows are created (zeroed) automatically
whenever a product or a warehouse is created, but nothing yet *writes* a
nonzero quantity or a stock movement — that lands with the
Purchases/Sales/Stock-Adjustment phases. See `docs/PROGRESS.md`.

## Backend structure

```
app/
  Http/Controllers/Api/V1/   Thin controllers (validate → call model/service → resource)
  Http/Requests/             Form Request validation, tenant-scoped uniqueness rules
  Http/Resources/            API response shaping (never expose raw models)
  Http/Middleware/           EnsureTenantContext
  Models/                    Eloquent models
  Models/Concerns/           BelongsToTenant trait
  Services/                  Cross-cutting business logic (TenantProvisioningService)
  Support/                   Tenant (per-request state), PermissionCatalog, RouteHelpers
database/migrations/         One table per migration, businesses before users before everything else
database/seeders/            PermissionSeeder (safe anywhere) / DemoDataSeeder (local/testing only)
tests/Feature/                Auth, tenant isolation, RBAC enforcement
```

## Frontend structure

```
src/
  app/(auth)/login|register       Public auth pages
  app/(dashboard)/...              Authenticated app shell (sidebar/topbar) + per-module pages
  components/ui/                   Button, Input/Select, Card, Badge — generic primitives
  components/layout/               Sidebar, Topbar
  components/entity/                Generic EntityListPage / EntityForm / EntityEditPage used by
                                     every "simple" catalog module to avoid repeating CRUD boilerplate
  lib/api.ts                       Typed fetch wrapper (base URL, bearer token, ApiError)
  lib/auth-context.tsx             AuthProvider/useAuth — session state, login/register/logout
  lib/use-entity-list.ts           Shared paginated-list-with-search hook
  types/                            TypeScript types mirroring the API resources
```

Next.js 16 detail worth noting: route params/searchParams are async
(`Promise`), and dynamic edit pages that need both the route `id` *and*
need to pass callbacks into a Client Component use React's `use()` hook
inside a `"use client"` page rather than an `async` Server Component —
Server Components cannot pass functions as props to Client Components.
This was a real bug caught by browser testing during development (see
`docs/PROGRESS.md`), not a design that was obvious upfront.
