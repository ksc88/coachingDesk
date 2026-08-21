# CoachingDesk — multi-tenant coaching SaaS (pilot)

India-first coaching management for competition batches: students, attendance + parent alerts, fees/receipts with per-coaching Razorpay, announcements, notes, enquiry CRM, staff, reports, and branded landing pages.

## Stack

- Laravel 12 (PHP 8.2+) modular monolith + Inertia React + TypeScript + Tailwind
- MySQL 8 / SQLite (dev) + Redis queues (optional; database queue works for pilot)
- Sanctum API `/api/v1` for future Android app
- Per-tenant Razorpay BYOK

## Quick start (local / XAMPP)

```bash
cd /opt/lampp/htdocs/coaching-mgt-sys
cp .env.example .env   # if needed
php artisan key:generate
# MySQL (recommended): create DB then set DB_* in .env
# /opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS coaching_desk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# DB_CONNECTION=mysql  DB_DATABASE=coaching_desk  DB_USERNAME=root  DB_PASSWORD=
php artisan migrate --seed
npm install --legacy-peer-deps
npm run build
```

See `docs/ops/database-setup.md` for MySQL details and stronger constraints.
Open http://127.0.0.1/coaching-mgt-sys/public (or `php artisan serve`).

### Demo logins (after `php artisan migrate --seed`)

| Role | Email | Password |
|------|-------|----------|
| Platform (you) | admin@coaching-saas.test | password |
| Owner | owner@demo-coaching.test | password |
| Teacher | teacher@demo-coaching.test | password |
| Parent | parent@demo-coaching.test | password |

Platform console: `/platform/coachings`  
Demo coaching page: `/c/demo-coaching`

### Onboard your first real client (UI)

1. Create your provider login once:  
   `php artisan platform:admin --name="You" --email=you@yourcompany.com`
2. Sign in → you land on **Provider console**.
3. Click **Onboard coaching** → fill coaching + owner details → create.
4. Share the one-time owner password securely; owner signs in at `/login`.
5. Use **Deactivate** / **Activate** to suspend a coaching; **Reset owner password** if they lock themselves out.

CLI alternative: `php artisan tenant:create` (see `docs/ops/onboarding.md`).

## Docker (VPS recommended)

```bash
docker compose up -d --build
```

See `docker-compose.yml`.

## Ops

- Backup: `scripts/backup.sh`
- Restore drill: `scripts/restore-drill.sh`
- Queue worker: `php artisan queue:work`
- CI: `.github/workflows/ci.yml`

## Product docs

- `docs/pilot-workflows.md` — approved pilot defaults
- `docs/ops/onboarding.md` — onboard a new coaching tenant
