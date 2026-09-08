# TrainHub Unified Login Integration — Final Role/System Mapping

This build keeps the V6 database compatibility and Dashboard/page-unresponsive performance fixes, while making Nureen/TrainHub the central login gateway for all five FYP systems.

## User flow

1. Open TrainHub `/login.php`.
2. Enter email + password once.
3. TrainHub discovers every active role belonging to that email.
4. Choose a role.
5. Choose one of the systems available for that role.
6. TrainHub opens its own Dashboard or sends a short-lived one-time handoff token to the selected friend system.
7. The friend application establishes the session/guard required by its own dashboard and redirects to the correct dashboard/page.

A selected role or system can be clicked again to unselect it. Both selection screens also include **Clear selection**. The system screen includes **Change role**.

## Final role-to-system mapping

| Role | Available systems |
|---|---|
| Teacher | Izz |
| New Teacher | Asyiqin, Izz |
| Outsider | Izz |
| Guru Besar | Asyiqin, Izz |
| Staff EDU | Nureen, Aidid, Izz |
| HR | Asyiqin, Athirah |
| Trainer | Aidid |
| Observer | Asyiqin |
| External Observer | Asyiqin |

## System names shown on the system-choice page

1. Nureen - Training Course Administration System (TrainHub Al Amin)
2. Aidid - Training Management System (Trainer Perspective)
3. Asyiqin - Teacher Employment Management System
4. Athirah - Human Resource Management
5. Izz - Educator training Management system (EduTrain system)

## Observer rule

Observer and External Observer are Teacher accounts. The central login first discovers the Teacher by email. It then checks `observer` and `external_observer` using that `teacherID` and only adds those roles when the corresponding assignment is active. A teacher without an active observer assignment will not see Observer or External Observer.

## Dashboard/page targets

- Nureen Staff EDU -> TrainHub `dashboard`
- Aidid Staff EDU -> `admin.dashboard`
- Aidid Trainer -> `trainer.dashboard`
- Asyiqin HR -> `hr.dashboard`
- Asyiqin New Teacher -> `new_teacher.dashboard`
- Asyiqin Guru Besar -> `principal.dashboard`
- Asyiqin Observer -> `observer.dashboard`
- Asyiqin External Observer -> `external.dashboard`
- Athirah HR -> `hr.home`
- Izz Teacher -> `dashboard.teacher`
- Izz New Teacher -> `dashboard.guru-baru`
- Izz Outsider -> `dashboard.outsider`
- Izz Guru Besar -> `dashboard.guru-besar`
- Izz Staff EDU -> the supplied Izz project currently implements the staff/admin page using local role `hr`, so the adapter maps central Staff EDU to `hr.dashboard` while keeping the unified role label as Staff EDU.

## Canonical identity sources

The gateway uses `users`, `staff_edu`, `teacher`, `guru_new`, `principal`, `hr_administrator`, `trainer`, `outsider`, `observer`, and `external_observer` from `alamin_main_database`.

## Security/session behavior

- Failed credentials clear previous unified authentication context.
- Only roles actually belonging to the authenticated email are displayed.
- Role/system access is checked server-side; manually changing a submitted role/system is rejected.
- Native TrainHub session ID regenerates after successful authentication.
- Staff EDU TrainHub login/logout continues to use `login_session` so existing audit triggers still work.
- Cross-project tokens are random, short-lived, and one-time use.
- Friend applications never receive the user's password or password hash.
- Every friend application creates the local session/guard format expected by its own dashboard.

## Local URLs in the supplied `.env`

- Nureen/TrainHub gateway: `http://127.0.0.1:8000`
- Izz: `http://127.0.0.1:8001`
- Athirah: `http://127.0.0.1:8002`
- Aidid: `http://127.0.0.1:8003`
- Asyiqin: `http://127.0.0.1:8004`

Friend adapter files are included under `integration/unified-login-friend-adapters/`.
