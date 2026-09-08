<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Models\Teacher;
use App\Models\Staff;
use App\Models\Principal;
use App\Models\HrAdministrator;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }

        $request->validate(['email' => 'required', 'password' => 'required']);

        $user = Teacher::where('email', $request->email)->first()
            ?? Staff::where('email', $request->email)->first()
            ?? Principal::where('email', $request->email)->first()
            ?? HrAdministrator::where('email', $request->email)->first();

        if (!$user) return back()->with('error', 'Email not found');
        if (isset($user->status) && strtolower($user->status) === 'berhenti') {
            return back()->with('error', 'Your account has been terminated/resigned. Please contact the administrator for assistance.');
        }
        if (isset($user->assign_status) && strtolower($user->assign_status) === 'berhenti') {
            return back()->with('error', 'Your account has been terminated/resigned. Please contact the administrator for assistance.');
        }
        if (isset($user->is_active) && $user->is_active == 0) {
            return back()->with('error', 'Your account has been deactivated. Please contact the administrator for assistance.');
        }
        if (!Hash::check($request->password, $user->password)) return back()->with('error', 'Wrong password');

        if (isset($user->role) && strtolower($user->role) === 'teacher') {
            $teacherStatus = Teacher::join('assign', 'teacher.teacherID', '=', 'assign.teacherID')
                ->where('teacher.teacherID', $this->getUserId($user))
                ->where('assign.status', 'Berhenti')
                ->first();
            if ($teacherStatus) {
                return back()->with('error', 'Your teacher account has been resigned. Please contact the administrator for assistance.');
            }
        }

        Session::put('user', $user);
        Session::put('userID', $this->getUserId($user));
        Session::put('userName', $this->getUserName($user));
        Session::put('role', strtolower($user->role));
        Session::put('email', $user->email);
        if (isset($user->schoolID)) Session::put('schoolID', $user->schoolID);

        if (isset($user->password_change_required) && $user->password_change_required == 1) {
            return redirect()->route('change.password');
        }
        return $this->redirectByRole($user->role);
    }

    private function getUserId($user)
    {
        return match(strtolower($user->role)) {
            'teacher' => $user->teacherID ?? null,
            'staff' => $user->staffID ?? null,
            'principal' => $user->principalID ?? null,
            'hr', 'hr_administrator' => $user->hrid ?? null,
            default => null,
        };
    }

    private function getUserName($user)
    {
        return match(strtolower($user->role)) {
            'teacher' => $user->teacherName ?? null,
            'staff' => $user->staffName ?? null,
            'principal' => $user->principalName ?? null,
            'hr', 'hr_administrator' => $user->username ?? null,
            default => null,
        };
    }

    public function showChangePassword()
    {
        if (!Session::has('user')) return redirect()->route('login');
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate(['password' => 'required|min:8|confirmed']);
        $role = strtolower((string) Session::get('role'));
        $email = Session::get('email');
        $record = match($role) {
            'teacher' => Teacher::where('email', $email)->first(),
            'staff' => Staff::where('email', $email)->first(),
            'principal' => Principal::where('email', $email)->first(),
            'hr', 'hr_administrator' => HrAdministrator::where('email', $email)->first(),
            default => null,
        };
        if (!$record) return back()->with('error', 'User record could not be found.');
        $record->password = Hash::make($request->password);
        $record->password_change_required = 0;
        $record->save();
        $record->refresh();
        Session::put('user', $record);
        Session::put('userID', $this->getUserId($record));
        Session::put('userName', $this->getUserName($record));
        Session::put('role', strtolower($record->role));
        Session::put('email', $record->email);
        if (isset($record->schoolID)) Session::put('schoolID', $record->schoolID);
        return $this->redirectByRole($record->role)->with('success', 'Password updated successfully');
    }

    private function redirectByRole($role)
    {
        return match(strtolower($role)) {
            'teacher' => redirect()->route('teacher.dashboard'),
            'principal' => redirect()->route('principal.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            'hr', 'hr_administrator' => redirect()->route('hr.home'),
            default => redirect()->route('login')->with('error', 'Invalid role: '.strtolower($role)),
        };
    }

    public function logout(Request $request)
    {
        Session::flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.logout_url'));
        }
        return redirect()->route('login');
    }
}
