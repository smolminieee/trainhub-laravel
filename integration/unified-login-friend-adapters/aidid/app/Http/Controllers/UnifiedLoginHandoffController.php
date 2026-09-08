<?php

namespace App\Http\Controllers;

use App\Services\UnifiedLoginHandoffService;
use App\Support\SharedTrainingSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnifiedLoginHandoffController extends Controller
{
    public function __construct(private readonly UnifiedLoginHandoffService $handoff) {}

    public function consume(Request $request): RedirectResponse
    {
        try {
            $payload = $this->handoff->consume((string) $request->query('token', ''), [
                'aidid_staff',
                'aidid_trainer',
            ]);

            $role = (string) ($payload['role'] ?? '');
            $identity = (array) ($payload['identity'] ?? []);
            $email = (string) ($payload['email'] ?? '');

            if (($payload['target_system'] ?? '') === 'aidid_trainer' && $role === 'trainer') {
                $trainer = DB::table('trainer')
                    ->where('trainerID', (string) ($identity['id'] ?? ''))
                    ->whereRaw('LOWER(TRIM(COALESCE(status, ?))) IN (?, ?)', ['active', 'active', 'aktif'])
                    ->first();

                if (!$trainer || strtolower((string) ($trainer->trainerEmail ?? '')) !== strtolower($email)) {
                    throw new \RuntimeException('The trainer account is no longer available.');
                }

                $trainer = SharedTrainingSchema::normaliseTrainer($trainer);
                $request->session()->regenerate();
                session([
                    'user_id' => $trainer->trainer_id,
                    'user_name' => $trainer->trainer_name,
                    'user_email' => $trainer->trainer_email,
                    'user_role' => 'trainer',
                    'unified_login' => true,
                ]);

                return redirect()->route('trainer.dashboard');
            }

            if (($payload['target_system'] ?? '') === 'aidid_staff' && $role === 'staff_edu') {
                $admin = DB::table('staff_edu')
                    ->where('staffID', (string) ($identity['id'] ?? ''))
                    ->whereRaw('LOWER(TRIM(COALESCE(status, ?))) IN (?, ?)', ['active', 'active', 'aktif'])
                    ->first();

                if (!$admin || strtolower((string) ($admin->email ?? '')) !== strtolower($email)) {
                    throw new \RuntimeException('The Staff EDU account is no longer available.');
                }

                $request->session()->regenerate();
                session([
                    'user_id' => $admin->staffID,
                    'user_name' => $admin->staffName,
                    'user_email' => $admin->email,
                    'user_role' => 'admin',
                    'unified_login' => true,
                ]);

                return redirect()->route('admin.dashboard');
            }

            throw new \RuntimeException('This role is not allowed in the selected Aidid workspace.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->away((string) config('unified_login.login_url'))
                ->with('error', 'Unified login could not be completed. Please sign in again.');
        }
    }
}
