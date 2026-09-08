<?php

namespace App\Http\Controllers;

use App\Models\HrAdministrator;
use App\Services\UnifiedLoginHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class UnifiedLoginHandoffController extends Controller
{
    public function __construct(private readonly UnifiedLoginHandoffService $handoff) {}

    public function consume(Request $request): RedirectResponse
    {
        try {
            $payload = $this->handoff->consume((string) $request->query('token', ''), ['tya']);
            if (($payload['role'] ?? '') !== 'hr_administrator') {
                throw new \RuntimeException('This role is not allowed in the Tya system.');
            }

            $identity = (array) ($payload['identity'] ?? []);
            $record = HrAdministrator::query()
                ->where('hrid', (string) ($identity['id'] ?? ''))
                ->whereRaw('LOWER(email) = ?', [strtolower((string) ($payload['email'] ?? ''))])
                ->first();

            if (!$record) {
                throw new \RuntimeException('The HR account is no longer available.');
            }

            $request->session()->regenerate();
            Session::put('user', $record);
            Session::put('userID', $record->hrid);
            Session::put('userName', $record->username);
            Session::put('role', 'hr');
            Session::put('email', $record->email);
            Session::put('unified_login', true);

            return redirect()->route('hr.home');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->away((string) config('unified_login.login_url'));
        }
    }
}
