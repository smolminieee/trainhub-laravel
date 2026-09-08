<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Observer;
use App\Models\ExternalObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }
        return view('auth.login');
    }

    public function store(Request $request)
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }

        $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        foreach (['admin', 'hr', 'new_teacher', 'principal', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) Auth::guard($guard)->logout();
        }

        if (Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password, 'role' => 'admin', 'status' => 'active'], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }
        if (Auth::guard('hr')->attempt(['email' => $request->email, 'password' => $request->password, 'role' => 'hr'], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('hr.dashboard');
        }
        if (Auth::guard('new_teacher')->attempt(['email' => $request->email, 'password' => $request->password, 'role' => 'new_teacher', 'current_status' => 'Active'], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('new_teacher.dashboard');
        }
        if (Auth::guard('principal')->attempt(['email' => $request->email, 'password' => $request->password, 'role' => 'principal'], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('principal.dashboard');
        }
        if (Auth::guard('teacher')->attempt(['email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $teacher = Auth::guard('teacher')->user();
            $observer = Observer::where('teacherID', $teacher->teacherID)->where('status', 'active')->first();
            if ($observer) {
                session(['teacher_role' => 'observer', 'observer_id' => $observer->observerID]);
                return redirect()->route('observer.dashboard');
            }
            $externalObserver = ExternalObserver::where('teacherID', $teacher->teacherID)->where('status', 'active')->first();
            if ($externalObserver) {
                session(['teacher_role' => 'external_observer', 'external_observer_id' => $externalObserver->externalObserverID]);
                return redirect()->route('external.dashboard');
            }
            Auth::guard('teacher')->logout();
            return back()->withInput($request->only('email'))->withErrors(['email' => 'You are not currently assigned as an observer.']);
        }

        throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
    }

    public function destroy(Request $request)
    {
        foreach (['admin', 'hr', 'new_teacher', 'principal', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) Auth::guard($guard)->logout();
        }
        $request->session()->forget(['teacher_role', 'observer_id', 'external_observer_id', 'unified_login']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.logout_url'));
        }
        return redirect()->route('login');
    }
}
