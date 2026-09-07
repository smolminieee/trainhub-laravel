<?php

namespace App\Http\Controllers;

use App\Services\UnifiedIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UnifiedAuthController extends Controller
{
    public function __construct(private readonly UnifiedIdentityService $identityService) {}

    public function login(Request $request): Response|RedirectResponse
    {
        if ($request->isMethod('get')) {
            if (!empty($_SESSION['unified_user']['email'] ?? '')) {
                return redirect()->route('auth.roles');
            }

            return response()->view('auth.unified-login', [
                'error' => '',
                'email' => '',
            ]);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return response()->view('auth.unified-login', [
                'error' => 'Please enter your email and password.',
                'email' => $email,
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->view('auth.unified-login', [
                'error' => 'Please enter a valid email address.',
                'email' => $email,
            ], 422);
        }

        try {
            $result = $this->identityService->attempt($email, $password);
        } catch (\Throwable $e) {
            report($e);
            return response()->view('auth.unified-login', [
                'error' => 'Unable to process the login at this time. Please try again.',
                'email' => $email,
            ], 500);
        }

        if (!($result['ok'] ?? false)) {
            return response()->view('auth.unified-login', [
                'error' => (string) ($result['error'] ?? 'Invalid email or password.'),
                'email' => $email,
            ], 422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->clearSelectedContext();
        $_SESSION['unified_user'] = $result['user'];
        $_SESSION['unified_authenticated_at'] = time();

        return redirect()->route('auth.roles');
    }

    public function roles(Request $request): Response|RedirectResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return redirect()->route('login');
        }

        $roleConfig = (array) config('unified_access.roles', []);
        $available = [];
        foreach ((array) ($user['roles'] ?? []) as $roleKey => $identity) {
            if (!isset($roleConfig[$roleKey])) {
                continue;
            }
            $available[$roleKey] = $roleConfig[$roleKey] + ['identity' => $identity];
        }

        if ($request->isMethod('post')) {
            $role = (string) $request->input('role', '');
            if (!isset($available[$role])) {
                return response()->view('auth.role-selection', [
                    'user' => $user,
                    'roles' => $available,
                    'error' => 'Please choose one of your available roles.',
                ], 422);
            }

            $_SESSION['selected_role'] = $role;
            unset($_SESSION['selected_system']);
            $this->applyRoleSessionAliases($role, (array) ($user['roles'][$role] ?? []));

            return redirect()->route('auth.systems');
        }

        return response()->view('auth.role-selection', [
            'user' => $user,
            'roles' => $available,
            'error' => '',
        ]);
    }

    public function systems(Request $request): Response|RedirectResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return redirect()->route('login');
        }

        $role = (string) ($_SESSION['selected_role'] ?? '');
        if ($role === '' || !isset(($user['roles'] ?? [])[$role])) {
            return redirect()->route('auth.roles');
        }

        $roleConfig = (array) config("unified_access.roles.{$role}", []);
        $systemConfig = (array) config('unified_access.systems', []);
        $systems = [];
        foreach ((array) ($roleConfig['systems'] ?? []) as $systemKey) {
            if (isset($systemConfig[$systemKey])) {
                $systems[$systemKey] = $systemConfig[$systemKey];
            }
        }

        if ($request->isMethod('post')) {
            $system = (string) $request->input('system', '');
            if (!isset($systems[$system])) {
                return response()->view('auth.system-selection', [
                    'user' => $user,
                    'roleKey' => $role,
                    'role' => $roleConfig,
                    'systems' => $systems,
                    'error' => 'Please choose one of the systems available for this role.',
                ], 422);
            }

            $_SESSION['selected_system'] = $system;
            $url = trim((string) ($systems[$system]['url'] ?? ''));

            if ($system === 'nureen') {
                $this->prepareNureenStaffSession($user, $role);
            }

            if ($url === '') {
                return response()->view('auth.system-placeholder', [
                    'user' => $user,
                    'roleKey' => $role,
                    'role' => $roleConfig,
                    'systemKey' => $system,
                    'system' => $systems[$system],
                ]);
            }

            return redirect()->to($url);
        }

        return response()->view('auth.system-selection', [
            'user' => $user,
            'roleKey' => $role,
            'role' => $roleConfig,
            'systems' => $systems,
            'error' => '',
        ]);
    }

    public function logout(): RedirectResponse
    {
        $staffID = (string) ($_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '');
        $loginSessionID = (int) ($_SESSION['loginSessionID'] ?? 0);

        try {
            if ($staffID !== '' && $loginSessionID > 0 && DB::getSchemaBuilder()->hasTable('login_session')) {
                DB::table('login_session')
                    ->where('sessionID', $loginSessionID)
                    ->where('staffID', $staffID)
                    ->where('sessionStatus', 'active')
                    ->update([
                        'logoutTime' => now(),
                        'sessionStatus' => 'ended',
                    ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }

        return redirect()->route('login');
    }

    private function authenticatedUser(): ?array
    {
        $user = $_SESSION['unified_user'] ?? null;
        return is_array($user) && !empty($user['email']) ? $user : null;
    }

    private function clearSelectedContext(): void
    {
        unset(
            $_SESSION['selected_role'],
            $_SESSION['selected_system'],
            $_SESSION['staffID'],
            $_SESSION['staffName'],
            $_SESSION['staff_id'],
            $_SESSION['staff_name'],
            $_SESSION['teacherID'],
            $_SESSION['teacherName'],
            $_SESSION['gn_id'],
            $_SESSION['applicant_id'],
            $_SESSION['outsider_id'],
            $_SESSION['principalID'],
            $_SESSION['hrid'],
            $_SESSION['hrID'],
            $_SESSION['trainerID'],
            $_SESSION['trainer_id'],
            $_SESSION['observerID'],
            $_SESSION['observer_id'],
            $_SESSION['externalObserverID'],
            $_SESSION['external_observer_id'],
            $_SESSION['role'],
            $_SESSION['loginSessionID']
        );
    }

    private function applyRoleSessionAliases(string $role, array $identity): void
    {
        // Remove aliases from any previously selected role while preserving the
        // authenticated unified identity and role list.
        $unified = $_SESSION['unified_user'] ?? null;
        $authenticatedAt = $_SESSION['unified_authenticated_at'] ?? null;
        $this->clearSelectedContext();
        $_SESSION['unified_user'] = $unified;
        $_SESSION['unified_authenticated_at'] = $authenticatedAt;
        $_SESSION['selected_role'] = $role;

        $id = (string) ($identity['id'] ?? '');
        $name = (string) ($identity['name'] ?? '');
        $_SESSION['role'] = $role;

        switch ($role) {
            case 'staff_edu':
                $_SESSION['staffID'] = $id;
                $_SESSION['staffName'] = $name;
                $_SESSION['staff_id'] = $id;
                $_SESSION['staff_name'] = $name;
                break;
            case 'teacher':
                $_SESSION['teacherID'] = $id;
                $_SESSION['teacherName'] = $name;
                break;
            case 'new_teacher':
                if (($identity['source'] ?? '') === 'applicant') {
                    $_SESSION['applicant_id'] = $id;
                } else {
                    $_SESSION['gn_id'] = $id;
                }
                break;
            case 'outsider':
                $_SESSION['outsider_id'] = $id;
                break;
            case 'principal':
                $_SESSION['principalID'] = $id;
                break;
            case 'hr_administrator':
                $_SESSION['hrid'] = $id;
                $_SESSION['hrID'] = $id;
                break;
            case 'trainer':
                $_SESSION['trainerID'] = $id;
                $_SESSION['trainer_id'] = $id;
                break;
            case 'observer':
                $_SESSION['observerID'] = $id;
                $_SESSION['observer_id'] = $id;
                $_SESSION['teacherID'] = (string) ($identity['teacherID'] ?? ($identity['teacher']['id'] ?? ''));
                break;
            case 'external_observer':
                $_SESSION['externalObserverID'] = $id;
                $_SESSION['external_observer_id'] = $id;
                $_SESSION['teacherID'] = (string) ($identity['teacherID'] ?? ($identity['teacher']['id'] ?? ''));
                break;
        }
    }

    private function prepareNureenStaffSession(array $user, string $role): void
    {
        if ($role !== 'staff_edu') {
            return;
        }

        $identity = (array) (($user['roles'] ?? [])['staff_edu'] ?? []);
        $staffID = (string) ($identity['id'] ?? '');
        $staffName = (string) ($identity['name'] ?? '');
        if ($staffID === '') {
            return;
        }

        $_SESSION['staffID'] = $staffID;
        $_SESSION['staffName'] = $staffName;
        $_SESSION['staff_id'] = $staffID;
        $_SESSION['staff_name'] = $staffName;
        $_SESSION['role'] = 'STAFF_EDU';

        if (!empty($_SESSION['loginSessionID'])) {
            return;
        }

        try {
            if (!DB::getSchemaBuilder()->hasTable('login_session')) {
                return;
            }

            DB::statement('SET @current_staff_id = ?, @current_staff_name = ?', [$staffID, $staffName]);
            $sessionID = DB::table('login_session')->insertGetId([
                'staffID' => $staffID,
                'loginTime' => now(),
                'sessionStatus' => 'active',
            ], 'sessionID');

            if ((int) $sessionID > 0) {
                $_SESSION['loginSessionID'] = (int) $sessionID;
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
