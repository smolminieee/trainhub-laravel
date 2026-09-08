<?php

namespace App\Http\Controllers;

use App\Models\ExternalObserver;
use App\Models\GuruNew;
use App\Models\HRAdministrator;
use App\Models\Observer;
use App\Models\Principal;
use App\Models\Teacher;
use App\Services\UnifiedLoginHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedLoginHandoffController extends Controller
{
    public function __construct(private readonly UnifiedLoginHandoffService $handoff) {}

    public function consume(Request $request): RedirectResponse
    {
        try {
            $payload = $this->handoff->consume((string) $request->query('token', ''), ['syiqin']);
            $role = (string) ($payload['role'] ?? '');
            $identity = (array) ($payload['identity'] ?? []);
            $email = strtolower((string) ($payload['email'] ?? ''));

            foreach (['admin', 'hr', 'new_teacher', 'principal', 'teacher'] as $guard) {
                if (Auth::guard($guard)->check()) {
                    Auth::guard($guard)->logout();
                }
            }

            if ($role === 'hr_administrator') {
                $record = HRAdministrator::query()
                    ->where('hrid', (string) ($identity['id'] ?? ''))
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->firstOrFail();
                Auth::guard('hr')->login($record);
                $request->session()->regenerate();
                $request->session()->put('unified_login', true);
                return redirect()->route('hr.dashboard');
            }

            if ($role === 'new_teacher') {
                $record = GuruNew::query()
                    ->where('gn_id', (string) ($identity['id'] ?? ''))
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('current_status', 'Active')
                    ->firstOrFail();
                Auth::guard('new_teacher')->login($record);
                $request->session()->regenerate();
                $request->session()->put('unified_login', true);
                return redirect()->route('new_teacher.dashboard');
            }

            if ($role === 'principal') {
                $record = Principal::query()
                    ->where('principalID', (string) ($identity['id'] ?? ''))
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->firstOrFail();
                Auth::guard('principal')->login($record);
                $request->session()->regenerate();
                $request->session()->put('unified_login', true);
                return redirect()->route('principal.dashboard');
            }

            if (in_array($role, ['observer', 'external_observer'], true)) {
                $teacherID = (string) ($identity['teacherID'] ?? '');
                $teacher = Teacher::query()
                    ->where('teacherID', $teacherID)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->firstOrFail();
                Auth::guard('teacher')->login($teacher);
                $request->session()->regenerate();
                $request->session()->put('unified_login', true);

                if ($role === 'observer') {
                    $observer = Observer::query()
                        ->where('observerID', (string) ($identity['id'] ?? ''))
                        ->where('teacherID', $teacherID)
                        ->where('status', 'active')
                        ->firstOrFail();
                    $request->session()->put('teacher_role', 'observer');
                    $request->session()->put('observer_id', $observer->observerID);
                    return redirect()->route('observer.dashboard');
                }

                $external = ExternalObserver::query()
                    ->where('externalObserverID', (string) ($identity['id'] ?? ''))
                    ->where('teacherID', $teacherID)
                    ->where('status', 'active')
                    ->firstOrFail();
                $request->session()->put('teacher_role', 'external_observer');
                $request->session()->put('external_observer_id', $external->externalObserverID);
                return redirect()->route('external.dashboard');
            }

            throw new \RuntimeException('This role is not allowed in the Syiqin system.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->away((string) config('unified_login.login_url'));
        }
    }
}
