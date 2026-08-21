# Pilot Workflow Defaults (Approved Seed)

These defaults replace the first coaching-owner discovery session until a live walkthrough confirms or overrides them. Freeze this scope for the pilot; anything else goes to the post-pilot backlog.

## 1. Coaching / batch structure

- Hierarchy: Organization → Branch → Category/Course → Batch → Subject → Class Session
- Competition example: `JEE Main`, `NEET`, `Foundation`
- Batch example: `JEE Morning 2026`, `NEET Evening 2026`
- Academic session: `2026-27` (1 Apr–31 Mar by default; tenant-configurable)
- One student may enrol in multiple batches; attendance and fees are batch-scoped

## 2. Fee and discount rules

- Fee plan attaches to a batch (monthly / quarterly / one-time installments)
- Student override: fixed discount amount or percentage with reason + approver
- Partial payments allowed; dues = invoice total − allocations − discounts + fines − waivers
- Modes: cash, bank transfer, UPI (manual entry), Razorpay (online)
- Each coaching uses **its own Razorpay account** (Model A BYOK)

## 3. Receipt format

- Immutable after issue; never hard-deleted
- Number format: `{ORG_CODE}/{FY}/{SEQ}` e.g. `DEMO/2526/00042`
- Sequence per tenant + financial year
- Fields: receipt no, date, student, batch, amount, mode, invoice refs, cashier, coaching GSTIN (optional)

## 4. Attendance correction rules

- Marked against a `class_session` (not just batch+date)
- Statuses: present, absent, late, leave, unmarked
- Finalize locks the sheet; corrections require reason and are audited
- Default notify: **absence immediately**; present alerts optional; daily summary optional

## 5. Notification wording / languages

- Default language: English; Hindi templates supported via tenant template overrides
- Channels: WhatsApp (opt-in required), SMS fallback, email, FCM push (future app)
- Absence sample: `{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}).`
- Announcement: batch / branch / category / org-wide with optional attachment

## 6. Guardian consent

- Explicit WhatsApp/SMS/email opt-in stored on guardian record
- Channel preferences and quiet hours (tenant setting)
- Consent captured at admission or via public form checkbox

## 7. Enquiry stages

`new` → `contacted` → `interested` → `demo_scheduled` → `admitted` | `lost`

- Required: name, phone, course interest, source
- Follow-up reminder via queue; convert to admission without re-keying

## 8. Required pilot reports

1. Daily / batch attendance summary
2. Fee defaulters and collections (by mode)
3. Enquiry pipeline and follow-ups due

## 9. Provider ownership (confirmed)

- Razorpay: per-coaching BYOK (Model A)
- WhatsApp: per-coaching sender preferred; platform templates for pilot demo
- SaaS subscription billing: platform's own Razorpay account (separate ledger)
