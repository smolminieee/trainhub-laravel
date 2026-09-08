# Unified Login Friend Adapters — 2026-09-08

These adapters are designed for the central Nureen/TrainHub unified login gateway.
All five systems must point to the same canonical MySQL database `alamin_main_database`.
The gateway uses the existing shared `cache` table for a short-lived, one-time handoff token.
No new authentication table and no shared `APP_KEY` are required.

## Final role-to-system mapping

- Teacher -> Izz
- New Teacher -> Asyiqin or Izz
- Outsider -> Izz
- Guru Besar -> Asyiqin or Izz
- Staff EDU -> Nureen, Aidid, or Izz
- HR -> Asyiqin or Athirah
- Trainer -> Aidid
- Observer -> Asyiqin
- External Observer -> Asyiqin

Observer and External Observer are not separate credential tables. They are Teacher identities that only receive those extra unified roles when an active `observer` or `external_observer` assignment exists for their `teacherID`.

## System names displayed by the gateway

1. Nureen - Training Course Administration System (TrainHub Al Amin)
2. Aidid - Training Management System (Trainer Perspective)
3. Asyiqin - Teacher Employment Management System
4. Athirah - Human Resource Management
5. Izz - Educator training Management system (EduTrain system)

## Local development ports used by the supplied TrainHub build

- Nureen / unified gateway: `http://127.0.0.1:8000`
- Izz: `http://127.0.0.1:8001`
- Athirah: `http://127.0.0.1:8002`
- Aidid: `http://127.0.0.1:8003`
- Asyiqin: `http://127.0.0.1:8004`

The ports are only a local convention. If the systems use Apache subfolders or different ports, change the corresponding `SYSTEM_*_URL` values in TrainHub and `UNIFIED_LOGIN_URL` / `UNIFIED_LOGOUT_URL` in each friend app.

## Files to add to every friend Laravel project

Copy these files from that friend's adapter folder into the matching Laravel paths:

- `app/Services/UnifiedLoginHandoffService.php`
- `app/Http/Controllers/UnifiedLoginHandoffController.php`
- `config/unified_login.php`
- `routes/unified_login.php`

Then add this line to that app's `routes/web.php`:

```php
require __DIR__.'/unified_login.php';
```

Merge these values into that app's `.env`:

```env
UNIFIED_LOGIN_ENABLED=true
UNIFIED_LOGIN_URL=http://127.0.0.1:8000/login.php
UNIFIED_LOGOUT_URL=http://127.0.0.1:8000/logout.php
```

Also make sure the app uses the canonical database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alamin_main_database
DB_USERNAME=root
DB_PASSWORD=
```

## Dashboard handoff by system

### Aidid

Based on `aidid-login-handoff.zip`.

- Central Staff EDU -> Aidid local `admin` session -> `admin.dashboard`
- Central Trainer -> Aidid local `trainer` session -> `trainer.dashboard`
- Existing expected session keys are populated: `user_id`, `user_name`, `user_email`, `user_role`.

### Izz

Based on the supplied `PSM-EDU-master.zip`.

- Teacher -> Spatie role `teacher` -> `dashboard.teacher`
- New Teacher -> Spatie role `guru_baru` -> `dashboard.guru-baru`
- Outsider -> Spatie role `outsider` -> `dashboard.outsider`
- Guru Besar -> Spatie role `guru_besar` -> `dashboard.guru-besar`
- Staff EDU -> the supplied Izz project currently implements its staff/admin page under local Spatie role `hr`; the adapter maps central **Staff EDU** to local `hr` -> `hr.dashboard`. The unified-login label remains Staff EDU.

### Athirah

Based on `login (1).zip`.

- HR -> the existing Athirah/Tya HR session shape (`user`, `userID`, `userName`, `role`, `email`) -> `hr.home`.

### Asyiqin

Based on `login syiqin.zip`.

- HR -> `hr` guard -> `hr.dashboard`
- New Teacher -> `new_teacher` guard -> `new_teacher.dashboard`
- Guru Besar -> `principal` guard -> `principal.dashboard`
- Observer -> `teacher` guard + `teacher_role=observer` -> `observer.dashboard`
- External Observer -> `teacher` guard + `teacher_role=external_observer` -> `external.dashboard`

## Security/session behavior

1. Password validation happens only once at the TrainHub gateway.
2. Failed credentials do not create a unified identity.
3. Only active roles discovered for the authenticated email are displayed.
4. Only systems allowed for the selected role are accepted server-side.
5. Handoff tokens are random, stored server-side in the shared canonical database, expire quickly, and are deleted on first use.
6. Each friend app creates its own normal local Laravel session after consuming the token.
7. Friend logout clears the local session and sends the browser to TrainHub logout so the central session is cleared too.
