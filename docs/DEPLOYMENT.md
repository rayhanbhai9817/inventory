# Deployment — Hostinger Cloud Hosting

This guide covers deploying the current state of the application (auth,
tenancy, RBAC, the inventory/catalog modules, and Suppliers + Product
Pricing — see `docs/PROGRESS.md` for what is and isn't live yet). It
assumes Hostinger's **Cloud Hosting** plan (hPanel-based), not a VPS.

## Why this plan shapes the setup

The frontend (Next.js) and backend (Laravel) are deployed to Hostinger
Cloud Hosting as described below. The **database is not hosted by
Hostinger** — it's a **Supabase-hosted PostgreSQL** database, reached
by Laravel over a standard Postgres connection string. Supabase is a
separate, independent service from Hostinger; nothing about Hostinger's
own database offering (or lack of one) constrains this. See
`docs/DATABASE.md` for exactly which values to copy from your Supabase
project and where.

## 1. Backend (Laravel)

1. In your Supabase project dashboard, go to **Settings → Database →
   Connection parameters** and note the host, port, database name,
   username, and the database password you set at project creation.
   See `docs/DATABASE.md` for the full field-by-field guide, including
   the 5432-vs-6543 port choice.
2. Upload the `backend/` directory to your Hostinger hosting account
   (Git deploy, SFTP, or hPanel's Git integration if available). The
   document root for the PHP app must point at `backend/public`.
3. On the server:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
4. Edit `.env` for production:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.yourdomain.com
   DB_CONNECTION=pgsql
   DB_HOST=<from Supabase Settings → Database>
   DB_PORT=5432
   DB_DATABASE=<from Supabase Settings → Database — usually "postgres">
   DB_USERNAME=<from Supabase Settings → Database — usually "postgres">
   DB_PASSWORD=<the database password you set when creating the Supabase project>
   DB_SSLMODE=require
   CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
   ```
   `DB_SSLMODE=require` is mandatory — Supabase does not accept
   unencrypted Postgres connections. Never type the real password into
   this repository, a commit, or a chat — it goes only into this one
   `.env` file on the production server (already gitignored).
5. Run migrations and seed the (tenant-safe) permission catalog only —
   **never** run `DemoDataSeeder` against production:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=PermissionSeeder --force
   php artisan app:sync-role-permissions
   ```
   The third command is required on **every** deploy that adds new
   permissions to the catalog (this one included — it added `suppliers.*`,
   `product_prices.*`, `supplier_reports.*`), not just the first deploy.
   `PermissionSeeder` only creates the global `Permission` rows;
   `app:sync-role-permissions` is what re-syncs each already-provisioned
   business's Owner/Manager/Staff roles to include them — a business that
   registered before this deploy will not see the new Suppliers/Product
   Prices sidebar items or be able to use those permissions until this
   runs. It's idempotent and safe to run on every deploy going forward,
   even ones that don't change the permission catalog.
6. Cache config/routes for performance:
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```
7. Point your web server (Apache via hPanel, or nginx on a more advanced
   setup) at `backend/public`, with PHP-FPM matching the `composer.json`
   requirement (`^8.3`), and with the **`pdo_pgsql`** PHP extension
   enabled (needed to reach the Supabase Postgres database — most
   general-purpose Hostinger PHP hosting has this available, but
   confirm in hPanel's PHP configuration screen).

### Production data safety

`.env` on the server contains the real database credentials and must
never be committed, emailed, or pasted into this repository, an issue,
or a chat log. `backend/.gitignore` already excludes `.env*`.

## 2. Frontend (Next.js)

Next.js needs a Node.js runtime. If Hostinger Cloud Hosting's plan
includes Node.js app hosting via hPanel, deploy `frontend/` there;
otherwise build a static/standalone output and serve it, or host the
frontend on a Node-capable platform while the API stays on Hostinger —
the two are independent deployments connected only by
`NEXT_PUBLIC_API_URL` / `CORS_ALLOWED_ORIGINS`.

```bash
cd frontend
npm install
echo "NEXT_PUBLIC_API_URL=https://api.yourdomain.com" > .env.production.local
npm run build
npm run start   # or serve the standalone output per your host's Node setup
```

## 3. First Admin User Setup

Do this **once**, right after step 1 (backend deployed, `.env` configured,
migrations run) and before you rely on the frontend register page for
anything. It creates your first Owner-level administrator directly in
the Hostinger production database over SSH — your password is never
typed anywhere but your own terminal, never sent to Claude, never
logged, and never committed to GitHub.

### A. The command

SSH into your Hostinger server, `cd` into the `backend/` directory you
deployed, and run:

```bash
php artisan app:create-admin
```

It will interactively prompt you (nothing here is a placeholder — this
is a real, tested Laravel console command at
`app/Console/Commands/CreateAdminCommand.php`):

```
Create Administrator Account
This creates a user with the highest-level "Owner" role, which has every permission.

No business exists yet — this will be the first one.
 Business name:
 > Ozipco Inventory
 Name:
 > Your Name
 Email:
 > you@yourdomain.com
 Password:
 >
 Confirm Password:
 >
```

- **Business name** — your company name (e.g. "Ozipco Inventory"). Only
  asked when no business exists yet in this database — i.e. exactly
  once, for your very first admin.
- **Name / Email** — typed input, echoed back to you as you type (not
  secret) so you can confirm they're correct.
- **Password / Confirm Password** — read via a masked prompt
  (`Command::secret()`); characters are not echoed to the terminal and
  Laravel never writes them to any log.

If you ever need a **second** admin later (e.g. a co-founder), run the
same command again. Since a business now exists, it will instead ask
you to pick that business from a numbered list or type `new` to create
another one — this is also how you'd bootstrap a second, separate
business if you ever host more than one tenant on this deployment.

### B. Do I need to run migrations first?

Yes — `php artisan migrate --force` (step 1.5 above) must already have
been run, so the `businesses`, `users`, and permission tables exist.
`app:create-admin` only inserts rows; it does not touch schema.

### C. Do I need to run seeders first?

Yes, exactly one: `php artisan db:seed --class=PermissionSeeder --force`
(also step 1.5). That seeds the global permission catalog (e.g.
`products.view`, `stock.in`, `settings.manage`) that roles are built
from. `app:create-admin` will still run without it, but the Owner role
it creates would have zero actual permissions attached, since none
would exist yet to attach. **Never** run `DemoDataSeeder` — see "Never
do this against production" below.

### D. Do I need to configure `.env`?

Yes — this must already be done (step 1.4): real `DB_*` credentials
pointing at your Supabase production PostgreSQL database (`DB_CONNECTION=pgsql`,
`DB_SSLMODE=require` — see `docs/DATABASE.md`), and `CORS_ALLOWED_ORIGINS`
set to your real frontend URL. `app:create-admin` writes through
whatever database connection your `.env` currently points at — that is
exactly why `.env` must be configured for the real Supabase production
database *before* you run it, not Claude's development database, not a
local SQLite file. There's no separate credential store: your
production `.env` is what makes this "the Supabase production
database."

### E. How the Next.js frontend connects to the Laravel API

Purely through `NEXT_PUBLIC_API_URL` (step 2), baked into the frontend
build at `npm run build` time. The browser calls
`{NEXT_PUBLIC_API_URL}/api/v1/...` directly with a Bearer token — there
is no server-side proxy or shared secret to configure beyond that URL
and the backend's `CORS_ALLOWED_ORIGINS` (step 1.4) allowing the
frontend's origin.

### F. How to verify login works

1. Open your deployed frontend URL → you should land on `/login`.
2. Sign in with the email and password you just set.
3. You should land on `/dashboard` with the full sidebar visible
   (Dashboard, Inventory, Stock IN/OUT, Products, Categories, Admin
   Users, Settings, etc.) — a Owner account has every permission, so
   every module should be visible immediately, with no reload required.
4. If you want to verify from the command line instead/first:
   ```bash
   curl -X POST https://api.yourdomain.com/api/v1/auth/login \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"email":"you@yourdomain.com","password":"<your password>"}'
   ```
   A successful response returns a `token` and a `user` object whose
   `roles` array contains `"Owner"` and whose `permissions` array is
   non-empty. (An empty `roles`/`permissions` array here would indicate
   a regression — this exact response shape is covered by an automated
   test, `tests/Feature/Auth/LoginTest.php`.)

### G. How to create additional users after logging in as Admin

Once logged in as Owner, use the app itself — no more SSH/CLI needed:
**Administration → Admin Users → Add User** in the sidebar. That page
lets you set a name, email, temporary password, and role (Owner /
Manager / Staff) for each additional teammate, and lets you deactivate
users or reset their password later. `app:create-admin` is only for
the initial bootstrap (or adding a business), because it's the only
path that works before any admin account exists yet to log in with.

## 4. Post-deploy checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_KEY` generated and **not** the same as any development key
- [ ] HTTPS enforced on both frontend and API domains (required for
      Bearer tokens to be safe in transit)
- [ ] `CORS_ALLOWED_ORIGINS` matches the real frontend origin exactly
- [ ] Database backups configured in hPanel before running migrations
      against a database that already has real data
- [ ] `php artisan migrate --force` output reviewed — never run a
      migration against production without understanding what it changes
- [ ] `php artisan db:seed --class=PermissionSeeder --force` run
- [ ] `php artisan app:sync-role-permissions` run (required on **every**
      deploy that adds permissions, including this one — see step 1.5)
- [ ] `php artisan app:create-admin` run over SSH, first Owner account
      created, login verified (see section 3)
- [ ] Storage/log directories writable by the web server user
- [ ] Queue/cron: not required yet (no queued jobs or scheduled commands
      exist in this phase)

## Never do this against production

- `php artisan migrate:fresh` (drops every table)
- `php artisan db:seed --class=DemoDataSeeder` (refuses to run outside
  local/testing anyway, but don't try to force it)
- Editing `.env` credentials without a database backup first
