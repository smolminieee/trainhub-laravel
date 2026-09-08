# TrainHub Al Amin - Laravel Version

This package contains the Laravel version of **TrainHub Al Amin** updated for the latest V6 canonical combined Al Amin database.

## Database used by TrainHub

TrainHub now reads the database name from Laravel's `.env` file. The supplied project is configured for:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="alamin_main_database"
DB_USERNAME=root
DB_PASSWORD=
```

The canonical database contains tables from several FYP systems. TrainHub does **not** need to use all of them. Its normal pages use the TrainHub/shared tables required by its own features, including `staff_edu`, `teacher`, `school`, `assign`, `guru_new`, `course`, `course_session`, `course_participant`, `trainer`, `session_trainer`, attendance tables, feedback tables, certificate tables, observer tables, `audit_log`, and `login_session`.

The normal `/login.php` route authenticates TrainHub EDU staff from `staff_edu` and records sessions in `login_session`.

## First-time setup in XAMPP

1. Start Apache and MySQL in XAMPP.
2. Import `database/reference/main_database_CANONICAL_PRE_INSERT_V6.sql` (or your identical V6 copy). It creates **`alamin_main_database`**.
3. Add/insert the application data required for your testing if you imported the pre-INSERT schema version.
4. Put this Laravel project inside your XAMPP `htdocs` folder.
5. Open a terminal in the project folder and run:

```bash
php artisan config:clear
php artisan cache:clear
```

6. Confirm `.env` points to `alamin_main_database` using the settings above.
7. Start Laravel as required by your existing XAMPP workflow, then open the TrainHub login page.

## Important after changing `.env`

Laravel can keep an old database name in its configuration cache. If you change `DB_DATABASE`, always run:

```bash
php artisan config:clear
```

If a cached configuration file still exists, clearing it prevents TrainHub from continuing to connect to the previous database.

## Performance updates in this package

The canonical database is larger because it contains the combined group schema, so several TrainHub queries were reduced to only the rows needed by the current page.

- Dashboard credit-hour calculation no longer repeatedly calls a stored function for every attendance row.
- Dashboard audit filtering uses an indexed date range instead of applying `DATE_FORMAT()` to every audit row.
- Dashboard training/calendar queries use direct scoped joins rather than loading broad view results.
- Training list no longer synchronizes every approved participant for every course on every page load.
- Course detail loads sessions, participants, and attendance only for the selected course.
- Trainer list uses the stored `averageRating` and loads documents only for trainers displayed on the current page.
- Feedback form statistics are aggregated once instead of using repeated correlated subqueries, and editable form questions are preloaded to avoid N+1 queries.
- Teacher detail queries are loaded only when a school/tab needs them.
- Settings credit-hour calculations are limited to the logged-in staff member.
- Legacy mysqli pages and Laravel/Eloquent now read the same database configuration from `.env`.

## Local database check

When `APP_ENV=local`, the project includes a diagnostic endpoint:

```text
/laravel-status
```

It reports the configured database name and counts from core TrainHub tables. Use it only for local development/testing.


### V6 compatibility
- `course`, `course_session`, and `trainer` now maintain the V6 `created_at` / `updated_at` columns.
- `sp_register_guru_new` timestamp handling is provided by the V6 database procedure.
- Normal TrainHub pages remain scoped to TrainHub-required/shared tables rather than unrelated group-module tables.
- The earlier performance fixes remain enabled.
