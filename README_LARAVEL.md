# TrainHub Al Amin — Laravel 12 Migration

This package converts the supplied TrainHub V4 application into a Laravel 12 project while preserving the existing `fyp2.0` MySQL database, current V4 UI, CRUD workflows, attendance, feedback, certificates, audit behaviour and certificate email flow.

## Target

Laravel 12 + PHP 8.2+ was chosen so the project remains compatible with the PHP 8.2-era XAMPP environment used by the FYP. The existing `fyp2.0` schema is reused; you do not need to recreate the database.

## Converted to native Laravel

- Laravel front controller and routing (`public/index.php`, `routes/web.php`)
- Controllers for every TrainHub page (`app/Http/Controllers`)
- Blade views for all pages (`resources/views`)
- Shared Blade topbar (`resources/views/partials/topbar.blade.php`)
- Eloquent model classes for the TrainHub/shared tables (`app/Models`)
- `.env`-based database, mail, session and application configuration
- Login / logout and forgot-password flow in `AuthController`
- Account Settings profile/password update in `SettingsController`
- Public attendance form in `AttendanceController`
- Certificate email delivery through Laravel Mail (`CertificateMailer`)
- CSRF protection on the native Laravel forms
- Public CSS/images under `public/assets`

## Compatibility layer for the largest modules

Dashboard, Teacher, Training, Trainer, Feedback and Certificate contain thousands of lines of tightly coupled SQL/business rules from the tested FYP version. Their requests now enter through Laravel controllers and their UI is rendered by Blade, but the existing module rules are temporarily isolated under:

```
app/Legacy/*.logic.php
```

This is a **safe migration bridge**, not a second standalone PHP application. It exists so the existing stored procedures, triggers, generated files and complex CRUD rules continue to work while the project runs inside Laravel.

If your lecturer requires **zero `mysqli` / zero compatibility code**, those six modules should be refactored next into Laravel service/repository classes and Query Builder/Eloquent queries. The current package already gives you the Laravel application structure needed for that second pass without changing the UI again.

## Existing database

The package connects directly to:

```text
fyp2.0
```

Do **not** run a fresh schema migration over your current FYP database. The existing tables, views, functions, procedures and triggers are expected to remain in place.

## Installation on Windows / XAMPP

1. Extract the project, for example:

```text
C:\xampp\htdocs\trainhub_laravel
```

2. Install Composer if it is not already installed.

3. Open Command Prompt/PowerShell in the project folder:

```bat
composer install
copy .env.example .env
php artisan key:generate
```

4. Check the database section in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="fyp2.0"
DB_USERNAME=root
DB_PASSWORD=
```

5. Start Laravel:

```bat
php artisan serve
```

6. Open:

```text
http://127.0.0.1:8000/login.php
```

### XAMPP Apache

Laravel must be served from the `public` directory. Without a VirtualHost you can use:

```text
http://localhost/trainhub_laravel/public/login.php
```

For a normal URL, point an Apache VirtualHost `DocumentRoot` to:

```text
C:/xampp/htdocs/trainhub_laravel/public
```

## Routes

The original `.php` URLs are kept so all existing links continue to work:

- `/login.php`
- `/dashboard.php`
- `/teacher.php`
- `/course.php`
- `/trainer.php`
- `/feedback.php`
- `/certificate.php`
- `/settings.php`
- `/attendance.php`
- `/logout.php`

Clean aliases such as `/dashboard`, `/trainings`, `/trainers`, `/feedback`, `/certificates` and `/settings` are also provided.

## Database check

In the `local` environment, open:

```text
/laravel-status
```

It reports the Laravel version, selected database and core Eloquent row counts.

## Certificate email

SMTP credentials are no longer hard-coded in PHP. Configure them only in `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=your_sender@gmail.com
MAIL_PASSWORD=your_new_google_app_password
MAIL_FROM_ADDRESS=your_sender@gmail.com
MAIL_FROM_NAME="TrainHub Al Amin"
```

Certificate sending uses Laravel's `Mail` facade.

### Security action required

The V4 ZIP supplied for conversion contained SMTP credentials in source code. They have been removed from this Laravel package. **Revoke the old Google App Password and create a new one before using email again.** Never commit the real password to GitHub; keep it in `.env` only.

## Main structure

```text
trainhub_laravel12/
├── app/
│   ├── Http/Controllers/
│   ├── Http/Middleware/
│   ├── Models/
│   ├── Services/
│   ├── Support/
│   └── Legacy/                 # temporary compatibility logic for 6 complex modules
├── bootstrap/
├── config/
├── database/
├── public/
│   ├── assets/
│   ├── uploads/
│   └── generated/
├── resources/views/
├── routes/
├── storage/
├── tests/
├── .env.example
├── artisan
└── composer.json
```

## Native Laravel modules in this package

| Feature | Laravel implementation |
|---|---|
| Login / logout | `AuthController` |
| Forgot password | `AuthController` |
| Settings | `SettingsController` + `StaffEdu` |
| Attendance | `AttendanceController` + Query Builder/Schema |
| Certificate email | `CertificateMailer` + Laravel Mail |
| Shared UI | Blade + public assets |

## Complex migrated modules

| Feature | Laravel entry point | Preserved rule layer |
|---|---|---|
| Dashboard | `DashboardController` | `app/Legacy/dashboard.logic.php` |
| Teacher | `TeacherController` | `app/Legacy/teacher.logic.php` |
| Training | `CourseController` | `app/Legacy/course.logic.php` |
| Trainer | `TrainerController` | `app/Legacy/trainer.logic.php` |
| Feedback | `FeedbackController` | `app/Legacy/feedback.logic.php` |
| Certificate | `CertificateController` | `app/Legacy/certificate.logic.php` |

## Composer/vendor note

The ZIP intentionally does not contain the generated `vendor/` directory. Run `composer install` after extracting it. This is the standard Laravel workflow and keeps the project package much smaller.
