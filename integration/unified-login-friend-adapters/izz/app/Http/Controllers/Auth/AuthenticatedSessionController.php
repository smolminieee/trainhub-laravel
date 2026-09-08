<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.login_url'));
        }

        $request->authenticate();
        $request->session()->regenerate();
        $role = $request->user()->getRoleNames()->first();

        $destination = match($role) {
            Role::GuruBesar->value => route('dashboard.guru-besar'),
            Role::Teacher->value => route('dashboard.teacher'),
            Role::GuruBaru->value => route('dashboard.guru-baru'),
            Role::HR->value => route('hr.dashboard'),
            default => route('dashboard.outsider'),
        };

        return Inertia::location($destination);
    }

    public function destroy(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.logout_url'));
        }

        return Inertia::location('/');
    }
}
