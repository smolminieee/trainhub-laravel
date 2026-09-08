# TrainHub Laravel - V6 Canonical Database Update

Database basis: `main_database_CANONICAL_PRE_INSERT_V6.sql` (`alamin_main_database`).

## V6 schema alignment
- Enabled Eloquent timestamps for `Course`, `CourseSession`, and `Trainer`.
- Course insert/update/status refresh now maintains `created_at` / `updated_at`.
- Course-session insert/update now maintains `created_at` / `updated_at`.
- Trainer insert/update/archive now maintains `created_at` / `updated_at`.
- Included the V6 canonical SQL under `database/reference/` for deployment reference.
- `sp_register_guru_new` timestamp handling lives in the V6 database procedure itself.

## Performance fixes retained
- Dashboard no longer calls `fn_calculate_credit_hour()` once per attendance row.
- Dashboard uses bounded/date-range queries and direct joins instead of repeatedly expanding broad views.
- Course list no longer performs global participant synchronization or loads every course's sessions/participants/attendance on every request.
- Feedback form list uses grouped summaries and preloads editable questions instead of correlated/N+1 queries.
- Trainer list uses cached `averageRating` and only loads documents for trainers on the current page.
- Teacher page defers teacher/observer detail queries until the relevant school/tab is opened.
- Settings calculates credit for only the logged-in staff member instead of expanding `v_staff_credit_hour`.
- Database connection timeout is configured to fail fast instead of leaving the browser hanging on an unavailable DB.

## Responsiveness note
These changes substantially reduce the causes that produced the previous browser `Page Unresponsive` behaviour. Absolute responsiveness still depends on the local MySQL/XAMPP environment, dataset size, and PHP extensions; a live MySQL server was not available in the packaging environment for end-to-end browser load testing.
