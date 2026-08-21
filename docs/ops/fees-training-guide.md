# Fees module — staff training guide

**CoachingDesk · Fees & receipts**  
Use this sheet to train desk staff. One case at a time. Tick each row when done.

---

## Before you start

1. Log in as the coaching owner or fee desk user.
2. Open **Fees** from the top menu.
3. You will use three tabs:
   - **Collect fee** — take money and print receipt
   - **Batch month** — raise monthly bills for a whole class
   - **More** — student ledger, payment history, special bills

**Tip:** Always search the student by name or admission number before typing the amount.

---

## Quick words you will see

| Word | Simple meaning |
|------|----------------|
| This month | Fee for the current calendar month |
| Older month (arrears) | Fee for a past month |
| Back dues | Fee for a month **before** the student joined (needs a note) |
| Partial | Student paid some, not all |
| Overdue | Due date has passed and money is still pending |
| Term | One lump-sum fee (not monthly) |
| Instalments | One big fee split into 2 or more bills |
| FIFO / Auto | System pays oldest unpaid bill first |

---

## Case 1 — Monthly fee, full payment

**When:** Student pays the full monthly fee for this month.

| Step | What to do |
|------|------------|
| 1 | **Students** → Add student |
| 2 | Pick a batch, Fee style = **Monthly**, enter monthly amount |
| 3 | Turn **off** “Raise bills on admission” (for this practice) |
| 4 | Save |
| 5 | **Fees → Collect fee** → search student |
| 6 | Payment for = **This month** |
| 7 | Amount = full monthly fee · Mode = Cash |
| 8 | Save & print receipt |

**Expect:** Receipt opens. Bill shows **paid**.

---

## Case 2 — Monthly fee, partial payment

**When:** Parent pays only part of this month’s fee.

| Step | What to do |
|------|------------|
| 1 | Add a monthly student (same as Case 1) |
| 2 | **Collect fee** → search student |
| 3 | This month · Amount = **less than** full fee (e.g. ₹500 of ₹1,400) |
| 4 | Mode = UPI · Save |

**Expect:** Receipt for partial amount. Balance still pending. Student may appear under **Reports → Overdue** after the due date.

---

## Case 3 — Older month / arrears (before join date)

**When:** Student joined in August but you need to record July fee from a previous centre.

| Step | What to do |
|------|------------|
| 1 | Add student with **Joined on** = 1 August (or later) |
| 2 | **Collect fee** → Payment for = **Older month (arrears)** |
| 3 | Pick **July** |
| 4 | Tick **Back dues** and type a short reason |
| 5 | Enter amount · Mode = Bank · Save |

**Expect:** Yellow warning is normal. With tick + note, payment is allowed. Receipt is issued.

**Do not use back dues** if the student actually joined in that month — set the correct join date instead.

---

## Case 4 — Custom due day (e.g. 15th)

**When:** Fees are due on the 15th of every month, not the 5th.

| Step | What to do |
|------|------------|
| 1 | Add monthly student · Tuition due day = **15th** |
| 2 | Collect this month’s full fee |

**Expect:** Bill due date is the **15th**. Before the 15th, unpaid bill is not “overdue”.

---

## Case 5 — Term / lump-sum fee

**When:** Student pays one package for the term (e.g. ₹15,000), not monthly.

| Step | What to do |
|------|------------|
| 1 | Add student · Fee style = **Term / lump sum** |
| 2 | Fee amount = full term amount (not the batch’s monthly default) |
| 3 | Tick **Raise bills on admission** · Save |
| 4 | **Collect fee** → pay the full term amount |

**Expect:** One big bill. After payment → **paid**.

**Note:** Batch dropdown may still show the monthly default (e.g. ₹1,400). For term students, type the **term amount** yourself.

---

## Case 6 — Instalments

**When:** Package is split into parts (e.g. ₹15,000 in 3 parts of ₹5,000).

| Step | What to do |
|------|------------|
| 1 | Add student · Fee style = **Instalments** · Total amount · **3 parts** |
| 2 | Tick Raise bills on admission · Save |
| 3 | **Fees → More** → open student ledger — you should see 3 bills |
| 4 | **Collect fee** → Amount = first part only |
| 5 | Optional: **Pin to a specific bill** → Instalment 1 |

**Expect:** First instalment paid (or partial if you pay less). Later instalments stay open / not due yet.

---

## Case 7 — Admission fee + first month

**When:** New student pays one-time admission fee and first month tuition.

| Step | What to do |
|------|------------|
| 1 | Add student · Monthly fee + **Admission fee** filled |
| 2 | Tick **Raise bills on admission** · Save |
| 3 | Collect admission amount first (or both if you pay the combined total with Auto) |
| 4 | Collect monthly tuition for this month |

**Expect:** Two separate bills — admission and monthly. Both can be paid.

---

## Case 8 — Batch month (whole class)

**When:** Start of month — raise bills for everyone in a batch at once.

| Step | What to do |
|------|------------|
| 1 | Add 1–2 monthly students with **Raise bills** turned **off** |
| 2 | **Fees → Batch month** |
| 3 | Pick the month · Click **Generate monthly dues** for that batch |
| 4 | Click again — system should **skip** students already billed |

**Expect:** New bills for students who did not have that month yet. Already billed students are skipped.

Then collect money one by one on **Collect fee**.

---

## Case 9 — Student in two batches (FIFO payment)

**When:** One student studies in two classes and pays one amount that covers both.

| Step | What to do |
|------|------------|
| 1 | Student must have **two active batches** (turn off “one batch per student” in Batches if needed) |
| 2 | Generate / create a bill for each batch |
| 3 | **Collect fee** → Amount bigger than the older bill |
| 4 | Pin = **Auto — oldest due first** · Save |

**Expect:** Older bill paid first; leftover goes to the next bill. Ledger may show one **paid** and one **partial**.

**Check:** **Fees → More** → student → **All batches** / each batch tab · **Payment history**.

---

## Everyday screens after collection

| Want to see… | Go to… |
|--------------|--------|
| This student’s dues by month | **Fees → More → Student fee ledger** |
| How much they paid and when | Same place → **Payment history** |
| Print an old receipt | Payment history **Print**, or **Recent receipts** |
| Who is overdue | **Reports → Overdue defaulters** |
| All open bills | **Reports → Pending dues** |
| Money collected this month | **Reports → Fees & collections** |
| All receipts | **Reports → Receipts** (search by name) |

---

## Collect fee fields (cheat sheet)

| Field | Use |
|-------|-----|
| Search student | Always select from the list |
| Payment for | This month **or** Older month |
| Which month | Only for Older month |
| Amount | What cash/UPI/bank you received |
| Mode | Cash / UPI / Bank |
| Paid on | Date money was received (can be back-dated) |
| Reference | Optional (UPI ref, cheque no.) |
| Pin to a specific bill | Leave Auto, or pick one bill |

---

## Common mistakes

1. **Wrong join date** → system thinks a normal month is “before join”.
2. **Term student left at monthly default** → amount looks like ₹1,400 instead of term total.
3. **Paying without searching** → always pick the student from the dropdown.
4. **Expecting one batch when coaching is “one batch per student”** → adding a batch replaces the old one.
5. **Thinking top “Collected” on Fees report is only for the searched student** → top total is for the whole coaching; table below follows your search.

---

## Practice checklist

Use this for training sessions. Create practice names like Case1, Case2… or use real demo students.

- [ ] Case 1 — Monthly full  
- [ ] Case 2 — Monthly partial  
- [ ] Case 3 — Back dues with note  
- [ ] Case 4 — Due on 15th  
- [ ] Case 5 — Term fee  
- [ ] Case 6 — Instalments (first part)  
- [ ] Case 7 — Admission + monthly  
- [ ] Case 8 — Batch month generate  
- [ ] Case 9 — Two batches, one payment  
- [ ] Open ledger + payment history + print receipt  

---

## Trainer notes

- Run cases in order the first time.
- After Case 7–9, show **Reports** so staff see the same money in reports.
- Online (Razorpay) is optional — desk usually uses Cash / UPI / Bank. Settings show if online pay is on or off.
- For daily class marking and parent absence alerts, use **docs/ops/attendance-training-guide.md**.
