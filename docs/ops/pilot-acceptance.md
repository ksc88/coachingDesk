# Pilot acceptance checklist

Run through this once against a fresh seed before inviting the first coaching owner.

## Functional

- [x] Public marketing page loads styled (`/`)
- [x] Branded coaching page accepts enquiry (`/c/demo-coaching`)
- [x] Owner login reaches `/app/dashboard`
- [x] Admit student with guardian WhatsApp opt-in
- [x] Student CSV import/export works and skips duplicates
- [x] Create class session and mark attendance (present / absent)
- [x] Absence creates notification outbox row with rendered template
- [x] `php artisan outbox:dispatch` sends pending messages (log channel in pilot)
- [x] Create invoice, record partial payment, issue immutable receipt `DEMO/{FY}/{SEQ}`
- [x] Razorpay BYOK settings save per tenant; cross-tenant gateway mismatch rejected
- [x] Publish org/batch announcement
- [x] Share note (file or URL)
- [x] Enquiry follow-up and convert-to-admission
- [x] Staff add + batch/subject assignment
- [x] Reports: attendance, collections, defaulters CSV, enquiry pipeline
- [x] Parent portal shows linked student data
- [x] `/api/v1/*` routes exist behind Sanctum for future Android app

## Security / tenancy

- [x] Tenant global scope hides other coaching students
- [x] User without `tenant_id` cannot open `/app/dashboard`
- [x] Login / enquiry / export / webhook endpoints are rate-limited
- [x] Payment gateway secrets cast as encrypted

## Ops

- [x] `scripts/backup.sh` writes SQLite + storage archive
- [x] `scripts/restore-drill.sh` restores non-destructively
- [x] `php artisan ops:status` reports outbox / webhook / queue health
- [x] CI workflow `.github/workflows/ci.yml` present
- [x] Docker Compose stack defined for VPS deploy

## Demo credentials

| Role | Email | Password |
|------|-------|----------|
| Owner | owner@demo-coaching.test | password |
| Teacher | teacher@demo-coaching.test | password |
| Parent | parent@demo-coaching.test | password |
| Platform | admin@coaching-saas.test | password |

## Remaining manual before first real client

1. Walk the first coaching owner through `docs/pilot-workflows.md` and adjust templates/receipt format.
2. Collect their Razorpay test keys and WhatsApp Business sender.
3. Deploy to a small VPS (not shared hosting) with Redis + queue worker + cron for scheduler.
4. Replace log message channel with live WhatsApp/SMS adapters.
