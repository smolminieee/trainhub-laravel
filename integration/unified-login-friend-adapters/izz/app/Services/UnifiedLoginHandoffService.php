<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnifiedLoginHandoffService
{
    private const CACHE_PREFIX = 'unified_login_handoff:';

    /** @param array<int, string> $allowedTargets */
    public function consume(string $token, array $allowedTargets): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 128 || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            throw new RuntimeException('The unified login token is invalid.');
        }

        if (!DB::getSchemaBuilder()->hasTable('cache')) {
            throw new RuntimeException('The shared cache table required for unified login is unavailable.');
        }

        $key = self::CACHE_PREFIX.hash('sha256', $token);

        return DB::transaction(function () use ($key, $allowedTargets): array {
            $row = DB::table('cache')->where('key', $key)->lockForUpdate()->first();
            if (!$row) {
                throw new RuntimeException('This unified login link is invalid or has already been used.');
            }

            // Consume first so the token cannot be replayed even when payload
            // validation fails after it has been retrieved.
            DB::table('cache')->where('key', $key)->delete();

            if ((int) ($row->expiration ?? 0) < time()) {
                throw new RuntimeException('This unified login link has expired.');
            }

            $payload = json_decode((string) ($row->value ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('The unified login payload is invalid.');
            }

            $target = (string) ($payload['target_system'] ?? '');
            $role = (string) ($payload['role'] ?? '');
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $identity = (array) ($payload['identity'] ?? []);

            if (!in_array($target, $allowedTargets, true)) {
                throw new RuntimeException('This unified login link is for another system.');
            }
            if ($role === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The unified login identity is incomplete.');
            }
            if ((int) ($payload['expires_at'] ?? 0) < time()) {
                throw new RuntimeException('This unified login link has expired.');
            }

            return $payload + ['identity' => $identity];
        });
    }
}
