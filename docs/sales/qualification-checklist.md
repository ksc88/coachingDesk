# Qualification + demo checklist (carry this to meetings)

## 1. Qualify in five questions

1. **"How many students, and how many batches?"**
   Sweet spot 50–2000. Under ~40 they won't pay. Over 2000 with 3+ branches, take it but expect feature demands.
2. **"What do you teach — one subject, competition prep, or school classes?"**
   Determines fit. See scenarios below.
3. **"How do you collect fees today, and how do you give receipts?"**
   If they already give numbered receipts and track dues, they'll value the fee module immediately.
   If they say "we just note it in a diary", the receipt/GST angle is your strongest pitch.
4. **"Do you have a Razorpay or payment gateway account?"**
   No account is fine — manual entry works from day one. Just sets expectations for online payment.
5. **"Who will enter data daily — you, or a receptionist?"**
   If nobody is assigned, the pilot will die quietly. Get a named person in the room.

## 2. Fit by scenario

**Subject-specific coaching (one subject, batches by timing/level) — sell now.**
Works with zero changes. Shortest sales cycle, owner decides alone. Start your first clients here.

**Competition coaching (JEE / NEET / SSC / banking) — sell now, with one caveat.**
Everything operational fits: courses, batches, per-subject teachers, per-subject attendance, branch-wise fees.
They *will* ask about test series, ranks and percentile — we don't have it yet.
Say plainly: "Test series isn't built yet. Today this handles attendance, fees and parent communication."
Never promise a date you haven't built.

**School-subject tuition PG–XII — qualify hard.**
GREEN: they run *whole-class* batches — one Class VIII batch, all subjects, one fee. Works today.
RED: students pick individual subjects from a class ("only Maths and Science"), or they expect report cards
with marks. Our enrolment and fees are per batch, not per subject. Walk away or park them until that's built.

## 3. Red flags — don't chase

- Wants marks, report cards or test ranks as a must-have (not built).
- Wants transport, hostel, library, payroll or board-exam management (out of scope by design).
- Single home tutor with 15 students (won't pay, expects free).
- No internet at the centre, or no smartphone with the person doing daily entry.
- Owner won't give parent phone numbers "for privacy" — the whole value depends on reaching parents.

## 4. Demo script (10 minutes)

1. **Create their institute live** in the provider console — name, code, owner email. Hand them the login.
   Takes under two minutes and lands better than any slide.
2. **Open their branded admissions page** `/c/their-slug` and submit a test enquiry from your phone.
   Show it appear in their enquiry list.
3. **Admit one student** with a guardian phone number (use the owner's own number).
4. **Mark that student absent** and show the alert going out to that number.
5. **Record a ₹1000 fee payment** and show the generated receipt number.
6. Stop. Don't tour every screen — book the follow-up instead.

**Before the first paid demo:** get one WhatsApp Business API provider account wired, or run the demo on SMS.
Right now outgoing messages are logged, not delivered. Do not demo notifications without this.

## 5. Objection responses

- *"Is my data safe on your server?"* — Each institute's data is isolated; nightly backups with a tested restore.
  You can export students and reports any time.
- *"Will parents actually see the messages?"* — Let's send one to your number right now.
- *"My receptionist isn't technical."* — Admitting a student is one form. Marking attendance is one screen. Let her try it now.
- *"What if the internet is down?"* — Honest answer: it needs a connection. Mark attendance from any phone on mobile data.
- *"What does it cost?"* — Free for three months as a pilot, then a flat monthly fee we agree up front. No lock-in.
- *"What if I stop paying?"* — Your account pauses, nothing is deleted, and it's all there when you return.

## 6. After they say yes

1. Create the institute in the provider console; share the owner password over a secure channel.
2. Sit with them for the first setup: branches, batches, fee plans, staff logins.
3. Import their student sheet; capture guardian consent while you do it.
4. Run one full loop with them watching: mark absent → parent message → record fee → receipt.
5. Book a check-in for day 7. Pilots die in week one or they survive.
