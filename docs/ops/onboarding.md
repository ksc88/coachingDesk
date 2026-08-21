# Onboard a new coaching tenant (pilot)

## Roles you need

| Who | Account type | How created | What they do |
|-----|--------------|-------------|--------------|
| **You (service provider)** | `platform_admin` — no `tenant_id` | `php artisan platform:admin` once | Onboard / activate / deactivate coachings, reset owner passwords |
| **Coaching owner** | `owner` — belongs to one tenant | Provider console or `tenant:create` | Runs that coaching: students, fees, staff, etc. |
| **Teachers / accountant / …** | staff roles under the same tenant | Owner creates them under **Staff** | Day-to-day ops for that coaching only |

Public `/register` does **not** create a coaching. Use the provider console.

## 1. Create your platform admin (once)

```bash
php artisan platform:admin \
  --name="Your Name" \
  --email=you@yourcompany.com
```

Sign in at `/login` → you land on `/platform/coachings`.

## 2. Onboard a coaching from the UI

1. Open **Provider console** → **Onboard coaching**.
2. Enter coaching name, receipt code (e.g. `XYZ`), slug, owner name/email/phone.
3. Leave password blank to auto-generate (shown once on the yellow credentials banner).
4. Share login + password with the owner over a secure channel.

Same result via CLI:

```bash
php artisan tenant:create \
  --name="XYZ Coaching Classes" \
  --code=XYZ \
  --slug=xyz-coaching \
  --owner-name="Owner Name" \
  --owner-email=owner@xyzcoaching.in
```

## 3. Activate / deactivate

On the coachings table:

- **Deactivate** → tenant `status = suspended`; their staff cannot sign in.
- **Activate** → restores access.
- **Reset owner password** → prints a new one-time password.

## 4. Owner-driven setup (no developer needed)

Owner signs in at `/login` and completes:

1. Academics → branches, categories, courses, subjects, batches.
2. Staff → teachers / accountant logins.
3. Fees → fee plans; paste their own Razorpay keys (BYOK).
4. Students → CSV import or manual admit; guardian consent for WhatsApp/SMS.
5. Announcements / Notes → smoke-test messaging.

## 5. Handover smoke test

Admit student → mark absent → outbox row → record fee → receipt `{CODE}/{FY}/{SEQ}` → parent portal shows attendance and dues.
