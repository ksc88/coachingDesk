# Database setup (MySQL)

## Recommended database name

**`coaching_desk`**

- Clear product name
- Avoids colliding with other XAMPP DBs
- UTF8MB4 for Hindi / emoji guardian names

Created as:

```sql
CREATE DATABASE coaching_desk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

## Local `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coaching_desk
DB_USERNAME=root
DB_PASSWORD=
```

XAMPP MySQL usually has empty root password. Set one before production.

## Fresh install

```bash
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS coaching_desk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd /opt/lampp/htdocs/coaching-mgt-sys
php artisan migrate --seed
php artisan platform:admin --name="You" --email=you@company.com
```

## Stronger constraints (migration)

`2026_07_31_100000_strengthen_coaching_schema_constraints.php` adds:

| Constraint | Purpose |
|------------|---------|
| Unique `academic_sessions (tenant_id, name)` | No duplicate year labels per coaching |
| Unique `courses (tenant_id, name)` | No duplicate course names |
| Unique `subjects (tenant_id, name)` | No duplicate subjects |
| Unique `batches (tenant_id, academic_session_id, name)` | Same batch name only once per session |
| Unique `guardians (tenant_id, phone)` | One guardian record per phone; link many students via pivot |
| Unique `staff_assignments (...)` | No duplicate teacher↔batch↔subject rows |
| Indexes on status / active flags | Faster dashboards and filters |
| MySQL CHECK on tenant/student/enrolment status, gender, fee ≥ 0, session dates | Hard DB-level validation |

## Old SQLite data

Previous local file (kept as backup):

`database/database.sqlite`  
`.env.sqlite.bak` — previous env pointing at SQLite

MySQL starts clean. Re-onboard coachings from Provider console, or import students via CSV.

## phpMyAdmin

Open http://127.0.0.1/phpmyadmin → database **`coaching_desk`**.
