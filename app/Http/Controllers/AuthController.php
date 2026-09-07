<?php

namespace App\Http\Controllers;

use App\Models\StaffEdu;
use App\Support\LegacyPageRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly LegacyPageRunner $runner) {}

    public function login(Request $request): Response|RedirectResponse
    {
        if ($request->isMethod('get')) {
            if (!empty($_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '')) {
                return redirect()->route('dashboard');
            }

            return response()->view('legacy.login', [
                'error' => '',
                'email' => '',
            ]);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $error = '';

        if ($email === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                /** @var StaffEdu|null $staff */
                $staff = StaffEdu::query()->where('email', $email)->first();

                if (!$staff) {
                    $error = 'Invalid email or password.';
                } elseif (strtolower((string) ($staff->status ?? 'inactive')) !== 'active') {
                    $error = 'Your account is inactive. Please contact the administrator.';
                } else {
                    $storedPassword = (string) $staff->password;
                    $info = password_get_info($storedPassword);
                    $storedIsHash = !empty($info['algo']);
                    $passwordMatched = $storedIsHash ? password_verify($password, $storedPassword) : false;
                    $matchedLegacyPlaintext = false;

                    if (!$passwordMatched && !$storedIsHash) {
                        $matchedLegacyPlaintext = hash_equals($storedPassword, $password);
                        $passwordMatched = $matchedLegacyPlaintext;
                    }

                    if (!$passwordMatched) {
                        $error = 'Invalid email or password.';
                    } else {
                        $loginSessionID = DB::transaction(function () use ($staff, $password, $storedPassword, $matchedLegacyPlaintext): int {
                            DB::statement('SET @current_staff_id = ?, @current_staff_name = ?', [
                                (string) $staff->staffID,
                                (string) $staff->staffName,
                            ]);

                            if ($matchedLegacyPlaintext || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                                DB::table('staff_edu')
                                    ->where('staffID', $staff->staffID)
                                    ->update(['password' => Hash::make($password)]);
                            }

                            return (int) DB::table('login_session')->insertGetId([
                                'staffID' => (string) $staff->staffID,
                                'loginTime' => now(),
                                'sessionStatus' => 'active',
                            ], 'sessionID');
                        });

                        if ($loginSessionID < 1) {
                            throw new \RuntimeException('The login session was not created.');
                        }

                        if (session_status() === PHP_SESSION_ACTIVE) {
                            session_regenerate_id(true);
                        }

                        $_SESSION['staffID'] = (string) $staff->staffID;
                        $_SESSION['staffName'] = (string) $staff->staffName;
                        $_SESSION['role'] = (string) ($staff->role ?? 'STAFF_EDU');
                        $_SESSION['loginSessionID'] = $loginSessionID;
                        $_SESSION['password_change_required'] = (int) ($staff->password_changed_required ?? 0);
                        $_SESSION['staff_id'] = $_SESSION['staffID'];
                        $_SESSION['staff_name'] = $_SESSION['staffName'];

                        return redirect()->route('dashboard');
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $error = 'Unable to process the login at this time. Please try again.';
            }
        }

        return response()->view('legacy.login', compact('error', 'email'), 422);
    }

    public function forgotPassword(Request $request): Response|RedirectResponse
    {
        $clearState = static function (): void {
            unset(
                $_SESSION['forgot_reset_staff_id'],
                $_SESSION['forgot_reset_staff_name'],
                $_SESSION['forgot_reset_expires']
            );
        };
        $normalizeIc = static fn (string $value): string => preg_replace('/[^0-9]/', '', $value) ?? '';

        if ($request->boolean('restart')) {
            $clearState();
            return redirect()->route('password.forgot');
        }

        $error = '';
        $success = $request->query('reset') === 'success'
            ? 'Your password has been reset successfully. You can now sign in using your new password.'
            : '';
        $identifier = '';
        $icNumber = '';

        $resetStaffID = (string) ($_SESSION['forgot_reset_staff_id'] ?? '');
        $resetStaffName = (string) ($_SESSION['forgot_reset_staff_name'] ?? '');
        $resetExpires = (int) ($_SESSION['forgot_reset_expires'] ?? 0);

        if ($resetStaffID !== '' && $resetExpires < time()) {
            $clearState();
            $resetStaffID = '';
            $resetStaffName = '';
            $error = 'Your password-reset session expired. Please verify your account again.';
        }

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', '');

            if ($action === 'verify_identity') {
                $identifier = trim((string) $request->input('identifier', ''));
                $icNumber = trim((string) $request->input('ic_number', ''));
                $cleanIcNumber = $normalizeIc($icNumber);
                $lockUntil = (int) ($_SESSION['forgot_lock_until'] ?? 0);

                if ($lockUntil > time()) {
                    $minutes = max(1, (int) ceil(($lockUntil - time()) / 60));
                    $error = 'Too many unsuccessful attempts. Please try again in approximately '.$minutes.' minute(s).';
                } elseif ($identifier === '' || $cleanIcNumber === '') {
                    $error = 'Please enter your email and IC number.';
                } elseif (strlen($identifier) > 100 || !filter_var($identifier, FILTER_VALIDATE_EMAIL) || strlen($cleanIcNumber) > 20) {
                    $error = 'The account information entered is invalid.';
                } else {
                    try {
                        $staff = StaffEdu::query()
                            ->where('email', $identifier)
                            ->whereRaw("REPLACE(REPLACE(ICNumber, '-', ''), ' ', '') = ?", [$cleanIcNumber])
                            ->first();

                        if ($staff && strtolower((string) ($staff->status ?? 'inactive')) === 'active') {
                            $_SESSION['forgot_reset_staff_id'] = (string) $staff->staffID;
                            $_SESSION['forgot_reset_staff_name'] = (string) $staff->staffName;
                            $_SESSION['forgot_reset_expires'] = time() + 600;
                            unset($_SESSION['forgot_failed_attempts'], $_SESSION['forgot_lock_until']);
                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_regenerate_id(true);
                            }
                            return redirect()->route('password.forgot');
                        }

                        $attempts = (int) ($_SESSION['forgot_failed_attempts'] ?? 0) + 1;
                        $_SESSION['forgot_failed_attempts'] = $attempts;
                        if ($attempts >= 5) {
                            $_SESSION['forgot_lock_until'] = time() + 600;
                            $_SESSION['forgot_failed_attempts'] = 0;
                        }
                        $error = 'The account details could not be verified. Please check the information and try again.';
                    } catch (\Throwable $e) {
                        report($e);
                        $error = 'Unable to verify the account at this time. Please try again.';
                    }
                }
            } elseif ($action === 'reset_password') {
                $resetStaffID = (string) ($_SESSION['forgot_reset_staff_id'] ?? '');
                $resetStaffName = (string) ($_SESSION['forgot_reset_staff_name'] ?? '');
                $resetExpires = (int) ($_SESSION['forgot_reset_expires'] ?? 0);
                $newPassword = (string) $request->input('new_password', '');
                $confirmPassword = (string) $request->input('confirm_password', '');

                if ($resetStaffID === '' || $resetExpires < time()) {
                    $clearState();
                    $resetStaffID = '';
                    $resetStaffName = '';
                    $error = 'Your password-reset session expired. Please verify your account again.';
                } elseif ($newPassword === '' || $confirmPassword === '') {
                    $error = 'Please enter and confirm your new password.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'The new password and confirmation do not match.';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'Your new password must contain at least 8 characters.';
                } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                    $error = 'Use at least one uppercase letter, one lowercase letter, and one number.';
                } elseif (strlen($newPassword) > 255) {
                    $error = 'The new password is too long.';
                } else {
                    try {
                        $staff = StaffEdu::query()->whereKey($resetStaffID)->first();
                        if (!$staff || strtolower((string) ($staff->status ?? 'inactive')) !== 'active') {
                            throw new \RuntimeException('The staff account is unavailable.');
                        }

                        $stored = (string) $staff->password;
                        $info = password_get_info($stored);
                        $sameAsCurrent = !empty($info['algo']) ? password_verify($newPassword, $stored) : hash_equals($stored, $newPassword);

                        if ($sameAsCurrent) {
                            $error = 'Your new password must be different from your current password.';
                        } else {
                            DB::transaction(function () use ($resetStaffID, $resetStaffName, $newPassword): void {
                                DB::statement('SET @current_staff_id = ?, @current_staff_name = ?', [$resetStaffID, $resetStaffName]);

                                $updated = DB::table('staff_edu')
                                    ->where('staffID', $resetStaffID)
                                    ->where('status', 'active')
                                    ->update([
                                        'password' => Hash::make($newPassword),
                                        'password_changed_required' => 0,
                                    ]);
                                if ($updated !== 1) {
                                    throw new \RuntimeException('The password was not updated.');
                                }

                                DB::table('login_session')
                                    ->where('staffID', $resetStaffID)
                                    ->where('sessionStatus', 'active')
                                    ->update(['logoutTime' => now(), 'sessionStatus' => 'ended']);

                                DB::table('audit_log')->insert([
                                    'staffID' => $resetStaffID,
                                    'userName' => $resetStaffName,
                                    'actionType' => 'UPDATE',
                                    'tableName' => 'staff_edu',
                                    'newValue' => 'Password reset completed and active sessions ended',
                                ]);
                            });

                            $clearState();
                            unset($_SESSION['forgot_failed_attempts'], $_SESSION['forgot_lock_until']);
                            return redirect()->route('password.forgot', ['reset' => 'success']);
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        $error = 'Unable to reset the password at this time. Please try again.';
                    }
                }
            } else {
                $error = 'Invalid password-reset request.';
            }
        }

        $resetStaffID = (string) ($_SESSION['forgot_reset_staff_id'] ?? '');
        $resetStaffName = (string) ($_SESSION['forgot_reset_staff_name'] ?? '');
        $resetExpires = (int) ($_SESSION['forgot_reset_expires'] ?? 0);
        $isResetStep = $resetStaffID !== '' && $resetExpires >= time();

        return response()->view('legacy.forgot_password', compact(
            'error', 'success', 'identifier', 'icNumber', 'resetStaffID', 'resetStaffName', 'resetExpires', 'isResetStep'
        ), $error !== '' && $request->isMethod('post') ? 422 : 200);
    }

    public function logout(Request $request): RedirectResponse
    {
        $staffID = (string) ($_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '');
        $loginSessionID = (int) ($_SESSION['loginSessionID'] ?? 0);

        try {
            if ($staffID !== '') {
                $query = DB::table('login_session')
                    ->where('staffID', $staffID)
                    ->where('sessionStatus', 'active');

                if ($loginSessionID > 0) {
                    $updated = (clone $query)
                        ->where('sessionID', $loginSessionID)
                        ->update(['logoutTime' => now(), 'sessionStatus' => 'ended']);

                    if ($updated === 0) {
                        $latest = $query->orderByDesc('loginTime')->orderByDesc('sessionID')->value('sessionID');
                        if ($latest !== null) {
                            DB::table('login_session')->where('sessionID', $latest)->update([
                                'logoutTime' => now(),
                                'sessionStatus' => 'ended',
                            ]);
                        }
                    }
                } else {
                    $latest = $query->orderByDesc('loginTime')->orderByDesc('sessionID')->value('sessionID');
                    if ($latest !== null) {
                        DB::table('login_session')->where('sessionID', $latest)->update([
                            'logoutTime' => now(),
                            'sessionStatus' => 'ended',
                        ]);
                    }
                }
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
}
