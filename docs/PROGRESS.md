# Implementation Progress

Status as of this phase. A module is only marked "Done" if it has real
migrations, a real API, real authorization, and (for anything with UI)
a real working frontend — no placeholders, no TODOs, no fake data.

## Done

| Module | Backend | Frontend | Notes |
|---|---|---|---|
| Project scaffold | ✅ | ✅ | Monorepo, `.gitignore`, `.env.example` x2, README x3 |
| Database schema | ✅ | — | All 25 tables migrated (see `docs/ARCHITECTURE.md`) |
| Multi-tenancy | ✅ | n/a | Row-level, fails closed, covered by tests |
| Authentication | ✅ | ✅ | Register/login/logout/me, Sanctum bearer tokens |
| Roles & permissions | ✅ | ✅ (UX-level `can()`) | Owner/Manager/Staff, per-business, Spatie teams |
| Categories | ✅ | ✅ | List/create/edit/delete |
| Brands | ✅ | ✅ | List/create/edit/delete |
| Units | ✅ | ✅ | List/create/edit/delete, delete blocked if in use |
| Warehouses | ✅ | ✅ | List/create/edit/delete, single-default enforced |
| Suppliers | ✅ | ✅ | List/create/edit/delete |
| Customers | ✅ | ✅ | List/create/edit/delete |
| Products | ✅ | ✅ | List/create/edit/delete, low-stock filter, per-warehouse stock rows |
| Dashboard | partial | ✅ | Real counts (products, low stock, suppliers, customers); no sales/revenue KPIs yet because sales/purchases don't exist |

Backend test suite: 12 tests / 41 assertions, covering registration,
login, tenant isolation (cross-tenant read/write blocked, slug
uniqueness correctly scoped per business), and RBAC enforcement
(Staff role blocked from delete/create-supplier, Owner allowed). Run with
`php artisan test`.

Frontend: `npm run lint`, `npx tsc --noEmit`, and `npm run build` all
pass. The full golden path (register → create category/unit/product →
edit → re-login) was exercised in a real browser (Playwright against the
running dev servers), which caught and led to fixing a real bug (see
"Bugs found and fixed" below) — this was not just a build-succeeds check.

## Not started

These are modeled in the database (migrations + Eloquent models exist)
but have **no API routes and no UI**:

- Purchases, purchase items, purchase returns
- Sales, sale items, sales returns
- Payments (against purchases/sales)
- Expenses / expense categories
- Stock adjustments & transfers (the inventory *engine* — the schema for
  `stock_movements` exists, but nothing currently writes a stock
  movement or a nonzero `product_stocks.quantity`)
- Audit logging (the `audit_logs` table and model exist; nothing writes
  to it yet)
- Reports (sales/purchase/inventory/profit/etc.)
- Multi-warehouse stock transfer UI
- File uploads (product images) — `image_path` column exists, unused
- Email verification / password reset (Sanctum + Laravel scaffolding
  supports this; no routes wired up)
- CI beyond the inherited `tests.yml` (backend PHPUnit on push) — no
  frontend CI job yet

## Known limitations

- Dashboard "stock value" is computed client-side over the first 100
  products only, and is explicitly labeled as such rather than presented
  as an authoritative figure — a real implementation belongs in a backend
  aggregate endpoint once reporting is built.
- Category hierarchy (`parent_id`) is supported by the API but has no
  picker in the frontend create/edit form yet.
- No rate limiting beyond login/register throttling.
- No automated frontend component/unit tests yet — only the manual
  Playwright golden-path run described above and the backend feature
  suite.

## Bugs found and fixed during this phase (for the record)

1. **Tenant scope leaked all data when no tenant context was set.** The
   original `BelongsToTenant` global scope only added a `WHERE business_id
   = ?` clause *when* a tenant was resolved, and did nothing otherwise —
   meaning any code path that forgot to set a tenant context would see
   every tenant's rows. Fixed to fail closed (`1 = 0`) instead. Caught by
   `tests/Feature/Tenancy/TenantIsolationTest.php`.
2. **`EnsureTenantContext` could run after route-model binding.** Laravel
   sorts middleware by an internal priority list; a custom middleware not
   in that list isn't guaranteed to run before `SubstituteBindings`. Fixed
   by explicitly registering it in the priority list (`bootstrap/app.php`).
3. **`Route::apiResource(...)->middleware($assocArray)` does not scope
   middleware per action** — it ignores the array keys and applies every
   value to every route, so a `show` route ended up requiring
   `create`+`edit`+`delete` permissions too. Fixed using the actual
   per-action API, `middlewareFor()` (`app/Support/RouteHelpers.php`).
4. **Spatie's `permission:` middleware defaulted to the `web` guard**,
   which is session-based and never authenticated for a bearer-token API
   request — every permission check appeared to be "not logged in."
   Fixed by pinning the guard explicitly (`permission:products.view,sanctum`).
5. **React Server Component passing a function prop to a Client
   Component.** The six entity edit pages were `async function` Server
   Components passing a `toValues` callback into the Client Component
   `EntityEditPage` — not allowed, and it silently crashed the page in
   the browser (caught only by an actual Playwright run, not by
   `next build`, which does not execute these client-render paths).
   Fixed by converting the edit pages to Client Components using React's
   `use()` hook to unwrap the async route params.

All five were caught by actually running the tests/app rather than
assuming the code was correct — several would not have been caught by
`tsc`, `eslint`, or `next build` alone.

## Recommended next phase

1. Purchases + inventory engine (the real "does stock move correctly"
   core) — this is the highest-value next slice per the master prompt's
   own phase ordering.
2. Sales, using the same pattern.
3. Returns, payments, expenses.
4. Dashboard/report aggregate endpoints once there's real transactional
   data to aggregate.
