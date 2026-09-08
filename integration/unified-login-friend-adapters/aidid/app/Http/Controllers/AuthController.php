<?php

namespace App\Http\Controllers;

use App\Support\SharedTrainingSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|in:trainer,admin',
        ]);

        if ($request->role === 'trainer') {
            $trainer = DB::table('trainer')->where('trainerEmail', $request->email)->first();
            if ($trainer) {
                $trainer = SharedTrainingSchema::normaliseTrainer($trainer);
            }

            if ($trainer && Hash::check($request->password, $trainer->trainerPassword)) {
                $request->session()->regenerate();
                session([
                    'user_id' => $trainer->trainer_id,
                    'user_name' => $trainer->trainer_name,
                    'user_email' => $trainer->trainer_email,
                    'user_role' => 'trainer',
                ]);
                return redirect()->route('trainer.dashboard');
            }
        }

        if ($request->role === 'admin') {
            $admin = DB::table('staff_edu')->where('email', $request->email)->first();
            if ($admin && Hash::check($request->password, $admin->password)) {
                $request->session()->regenerate();
                session([
                    'user_id' => $admin->staffID,
                    'user_name' => $admin->staffName,
                    'user_email' => $admin->email,
                    'user_role' => 'admin',
                ]);
                return redirect()->route('admin.dashboard');
            }
        }

        return back()->with('error', 'Invalid email, password, or role selected.');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (config('unified_login.enabled', true)) {
            return redirect()->away((string) config('unified_login.logout_url'));
        }

        return redirect()->route('login')->with('success', 'You have logged out successfully.');
    }
}
