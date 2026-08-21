# Attendance & parent alerts — staff training guide

**CoachingDesk · Daily class marking**  
Use this sheet at the desk. Tick each practice case when done.

---

## Before you start

1. Log in as owner or teacher.
2. Open **Attendance**.
3. Students must already be in the batch (active).
4. Parent alerts stay in **safe mode** until WhatsApp is connected — parents are **not** messaged for real during training.

---

## Daily flow

1. **Attendance** → New class session → pick batch, date, optional subject/topic → **Create sheet**
2. Open **Mark**
3. Tap **present / absent / late / leave** for each student  
   (Unmarked students default to present when you first open the sheet.)
4. Leave **Notify absent** ticked. Leave **Notify present** off (too many messages).
5. **Save attendance** once to keep a draft.
6. Fix any mistake, then tick **Finalize / lock** and save again.

After finalize, the class is locked. Marks cannot be changed on this screen.

---

## Practice cases

### Case A — Mark a mixed class

| Step | What to do |
|------|------------|
| 1 | Create today’s session for one batch |
| 2 | Mark some Present, one Absent, one Late, one Leave |
| 3 | Save **without** finalize |

**Expect:** Sheet saved. You can still change marks.

---

### Case B — Correct a mistake, then lock

| Step | What to do |
|------|------------|
| 1 | Change Late → Present |
| 2 | Tick **Finalize / lock** |
| 3 | Save |

**Expect:** Class status becomes completed. Buttons are disabled. Saving again shows that the class is locked.

---

### Case C — Absent parent alert (safe mode)

| Step | What to do |
|------|------------|
| 1 | Mark at least one student **absent** with Notify absent on |
| 2 | Save |
| 3 | Open **Parent alerts** (link on the attendance page) |

**Expect:** One queued/logged message per opted-in parent. Channel is **WhatsApp** if the parent ticked WhatsApp on admission. If not, **email** (if they gave an email and email opt-in). No consent → no alert. Message names the student, batch, date, and class.

This is **not** a live WhatsApp/SMS. Safe mode only writes to the queue / log.

---

### Case D — Present should not spam parents

Keep **Notify present** off. Only absent students get an alert.

---

## Where to look

| Want to see… | Go to… |
|--------------|--------|
| Today’s classes | **Attendance** list |
| Change marks (before lock) | **Mark** on that session |
| Queued / logged parent messages | **Parent alerts** or **Settings → Open alert queue** |
| Turn live send on/off | **Settings → Parent alerts** (keep Safe until WhatsApp is approved) |

---

## How alerts are chosen

One message only — never WhatsApp and email together.

1. WhatsApp, if the parent opted in and has a phone  
2. Else email, if they opted in and have an email  
3. Else nothing  

---

## Common mistakes

1. Finalizing too early — you cannot edit after lock.
2. Turning on **Notify present** — parents get a message for every present student.
3. Assuming the alert already reached WhatsApp — in safe mode it is only queued/logged.
4. Student with no parent phone/email/opt-in — no alert will appear.

---

## Practice checklist

- [ ] Case A — mixed marks, save draft  
- [ ] Case B — correct then finalize  
- [ ] Case C — absent alert visible on Parent alerts  
- [ ] Case D — present does not create an extra alert  
- [ ] Confirm Settings still say **Safe mode**

---

## When real WhatsApp goes live (WaAPI)

Fill **Settings → Parent alerts** from your [WaAPI](https://waapi.app) account:

| CoachingDesk field | Where you get it |
| --- | --- |
| Provider | Type exactly `waapi` |
| Instance ID | WaAPI dashboard → your instance number (e.g. `102486`) |
| Access token | WaAPI → **User API Tokens** → create/copy Bearer token |
| Send mode | Keep **Safe** until the instance shows **ready** (QR scanned). Then switch to **Live** and test to your own phone first. |

Also run a queue worker (`php artisan queue:work`) or `php artisan outbox:dispatch` so pending alerts actually send.

Until credentials are correct and tested, keep Safe mode on.
