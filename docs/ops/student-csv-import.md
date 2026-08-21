# Student CSV import

## Download

From **Students → Download CSV template**, or open:

`/app/students/import-template.csv`

Static copy also lives at `public/templates/students-import-template.csv`.

## Columns (header row required, exact names)

| Column | Required? | Notes |
|--------|-----------|-------|
| `admission_no` | Yes | Unique per coaching. Duplicates are skipped. |
| `first_name` | Yes | |
| `last_name` | No | |
| `class_level` | No | e.g. VIII, X, XII, Dropper |
| `school_name` | No | School / college name |
| `target_exam_year` | No | e.g. 2027 |
| `date_of_birth` | No | Prefer `YYYY-MM-DD` (e.g. 2009-05-14) |
| `gender` | No | `male`, `female`, or `other` |
| `phone` | No | If phone already exists, row is skipped as duplicate |
| `email` | No | |
| `address` | No | Wrap in quotes if it contains commas |
| `source` | No | Referral, pamphlet, walk-in… |
| `batch` | No | Must match an **existing batch name** exactly, or enrolment is skipped |
| `guardian_name` | With phone | Both name + phone needed to create guardian |
| `guardian_relation` | No | `father`, `mother`, or `guardian` |
| `guardian_phone` | With name | |
| `guardian_alternate_phone` | No | |
| `status` | No | Export-only today; import always creates `active` |

## Tips

1. Create batches first under **Batches**, then put the exact batch name in the `batch` column.
2. Keep the header row as-is; do not rename columns.
3. Save as CSV (UTF-8) from Excel / Google Sheets.
4. Import skips blank admission numbers and duplicate admission/phone rows; success message shows created vs skipped counts.
