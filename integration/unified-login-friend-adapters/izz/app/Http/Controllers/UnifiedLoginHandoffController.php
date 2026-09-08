<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\UnifiedLoginHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UnifiedLoginHandoffController extends Controller
{
    public function __construct(private readonly UnifiedLoginHandoffService $handoff) {}

    public function consume(Request $request): RedirectResponse
    {
        try {
            $payload = $this->handoff->consume((string) $request->query('token', ''), ['izz']);
            $centralRole = (string) ($payload['role'] ?? '');
            $email = strtolower((string) ($payload['email'] ?? ''));
            $name = trim((string) ($payload['name'] ?? '')) ?: $email;

            $localRole = match ($centralRole) {
                'teacher' => Role::Teacher->value,
                'new_teacher' => Role::GuruBaru->value,
                'outsider' => Role::Outsider->value,
                'principal' => Role::GuruBesar->value,
                'staff_edu' => Role::HR->value,
                default => throw new \RuntimeException('This role is not allowed in the Izz system.'),
            };

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if (!$user) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    // The central gateway remains the credential authority. This
                    // random local hash is never sent to the user.
                    'password' => Hash::make(Str::random(64)),
                    'external_id' => (string) (($payload['identity']['id'] ?? '') ?: null),
                ]);
            }

            if (!$user->hasRole($localRole)) {
                $user->assignRole($localRole);
            }

            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $request->session()->put('unified_login', true);

            return match ($localRole) {
                Role::GuruBesar->value => redirect()->route('dashboard.guru-besar'),
                Role::Teacher->value => redirect()->route('dashboard.teacher'),
                Role::GuruBaru->value => redirect()->route('dashboard.guru-baru'),
                Role::HR->value => redirect()->route('hr.dashboard'),
                default => redirect()->route('dashboard.outsider'),
            };
        } catch (\Throwable $e) {
            report($e);
            return redirect()->away((string) config('unified_login.login_url'));
        }
    }
}
