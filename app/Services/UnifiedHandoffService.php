<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnifiedHandoffService
{
    private const CACHE_PREFIX = 'unified_login_handoff:';

    public function issue(string $system, string $role, array $identity, array $user): string
    {
        if ($system === '' || $role === '') {
            throw new RuntimeException('Unified login handoff target is invalid.');
        }

        if (!DB::getSchemaBuilder()->hasTable('cache')) {
            throw new RuntimeException('The shared cache table required for unified login handoff is unavailable.');
        }

        $token = $this->randomToken();
        $cacheKey = self::CACHE_PREFIX.hash('sha256', $token);
        $expiresAt = time() + max(30, (int) config('unified_access.handoff_ttl', 90));

        $payload = [
            'version' => 1,
            'target_system' => $system,
            'role' => $role,
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'name' => trim((string) (($identity['name'] ?? '') ?: ($user['name'] ?? ''))),
            'identity' => [
                'id' => (string) ($identity['id'] ?? ''),
                'source' => (string) ($identity['source'] ?? ''),
                'teacherID' => (string) ($identity['teacherID'] ?? ($identity['teacher']['id'] ?? '')),
            ],
            'issued_at' => time(),
            'expires_at' => $expiresAt,
        ];

        if ($payload['email'] === '' || $payload['identity']['id'] === '') {
            throw new RuntimeException('Unified login identity is incomplete.');
        }

        DB::table('cache')->updateOrInsert(
            ['key' => $cacheKey],
            [
                'value' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'expiration' => $expiresAt,
            ]
        );

        return $token;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
