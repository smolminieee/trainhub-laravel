# TrainHub V4 → Laravel 12 Mapping

| Original page | Laravel controller | Blade view | Status |
|---|---|---|---|
| `login.php` | `AuthController@login` | `legacy.login` | Native Laravel |
| `forgot_password.php` | `AuthController@forgotPassword` | `legacy.forgot_password` | Native Laravel |
| `logout.php` | `AuthController@logout` | — | Native Laravel |
| `settings.php` | `SettingsController@index` | `legacy.settings` | Native Laravel |
| `attendance.php` | `AttendanceController@index` | `legacy.attendance` | Native Laravel |
| `dashboard.php` | `DashboardController@index` | `legacy.dashboard` | Laravel entry + preserved rule layer |
| `teacher.php` | `TeacherController@index` | `legacy.teacher` | Laravel entry + preserved rule layer |
| `course.php` | `CourseController@index` | `legacy.course` | Laravel entry + preserved rule layer |
| `trainer.php` | `TrainerController@index` | `legacy.trainer` | Laravel entry + preserved rule layer |
| `feedback.php` | `FeedbackController@index` | `legacy.feedback*` | Laravel entry + preserved rule layer |
| `certificate.php` | `CertificateController@index` | `legacy.certificate` | Laravel entry + preserved rule layer; mail native Laravel |

Core Eloquent models are in `app/Models`. The six preserved rule-layer files are isolated in `app/Legacy` so they can be refactored to Query Builder/Eloquent later without changing routes or Blade views.
