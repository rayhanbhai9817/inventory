# Deployment — Hostinger Cloud Hosting

This guide covers deploying the current state of the application (auth,
tenancy, RBAC, and the catalog modules — see `docs/PROGRESS.md` for what
is and isn't live yet). It assumes Hostinger's **Cloud Hosting** plan
(hPanel-based), not a VPS.

## Why this plan shapes the setup

Hostinger Cloud Hosting provisions **MySQL** databases through hPanel —
there is no managed PostgreSQL on this tier (that requires a VPS with
root access). The backend targets MySQL 8 in production for this reason;
see `README.md` → "Why MySQL, not PostgreSQL" for the fuller rationale.

## 1. Backend (Laravel)

1. In hPanel, create a MySQL database and user; note host/db/user/password.
2. Upload the `backend/` directory to your hosting account (Git deploy,
   SFTP, or hPanel's Git integration if available). The document root
   for the PHP app must point at `backend/public`.
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
   DB_CONNECTION=mysql
   DB_HOST=<hPanel MySQL host>
   DB_PORT=3306
   DB_DATABASE=<hPanel database name>
   DB_USERNAME=<hPanel database user>
   DB_PASSWORD=<hPanel database password>
   CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
   ```
5. Run migrations and seed the (tenant-safe) permission catalog only —
   **never** run `DemoDataSeeder` against production:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=PermissionSeeder --force
   ```
6. Cache config/routes for performance:
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```
7. Point your web server (Apache via hPanel, or nginx on a more advanced
   setup) at `backend/public`, with PHP-FPM matching the `composer.json`
   requirement (`^8.3`).

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

## 3. Post-deploy checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_KEY` generated and **not** the same as any development key
- [ ] HTTPS enforced on both frontend and API domains (required for
      Bearer tokens to be safe in transit)
- [ ] `CORS_ALLOWED_ORIGINS` matches the real frontend origin exactly
- [ ] Database backups configured in hPanel before running migrations
      against a database that already has real data
- [ ] `php artisan migrate --force` output reviewed — never run a
      migration against production without understanding what it changes
- [ ] Storage/log directories writable by the web server user
- [ ] Queue/cron: not required yet (no queued jobs or scheduled commands
      exist in this phase)

## Never do this against production

- `php artisan migrate:fresh` (drops every table)
- `php artisan db:seed --class=DemoDataSeeder` (refuses to run outside
  local/testing anyway, but don't try to force it)
- Editing `.env` credentials without a database backup first
