<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UnifiedIdentityService
{
    /** @var array<string, array<int, string>> */
    private array $columns = [];

    public function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $sources = (array) config('unified_access.credential_sources', []);
        $matched = null;
        $inactiveMatched = false;

        foreach ($sources as $sourceKey => $source) {
            $source = (array) $source;
            $row = $this->findByEmail($source, $email);
            if (!$row) {
                continue;
            }

            if (!$this->sourceIsActive($source, $row)) {
                $inactiveMatched = true;
                continue;
            }

            $passwordColumn = $this->firstExistingColumn(
                (string) ($source['table'] ?? ''),
                (array) ($source['password'] ?? [])
            );

            if (!$passwordColumn) {
                continue;
            }

            $stored = (string) ($row->{$passwordColumn} ?? '');
            if (!$this->passwordMatches($password, $stored)) {
                continue;
            }

            $this->upgradeLegacyPasswordIfNeeded($source, $row, $passwordColumn, $stored, $password);

            $matched = [
                'source_key' => (string) $sourceKey,
                'source' => $source,
                'row' => $row,
            ];
            break;
        }

        if (!$matched) {
            return [
                'ok' => false,
                'error' => $inactiveMatched
                    ? 'Your account is inactive. Please contact the administrator.'
                    : 'Invalid email or password.',
            ];
        }

        $roles = $this->discoverRoles($email);
        if ($roles === []) {
            return [
                'ok' => false,
                'error' => 'Your account was verified, but no active system role is currently assigned to this email.',
            ];
        }

        $source = $matched['source'];
        $row = $matched['row'];
        $table = (string) ($source['table'] ?? '');
        $nameColumn = $this->firstExistingColumn($table, (array) ($source['name'] ?? []));

        return [
            'ok' => true,
            'user' => [
                'email' => $email,
                'name' => trim((string) ($nameColumn ? ($row->{$nameColumn} ?? '') : '')) ?: $email,
                'authenticated_source' => $matched['source_key'],
                'roles' => $roles,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function discoverRoles(string $email): array
    {
        $roles = [];

        if ($identity = $this->identityFromSource('staff_edu', $email)) {
            $roles['staff_edu'] = $identity;
        }

        if ($teacher = $this->identityFromSource('teacher', $email)) {
            $roles['teacher'] = $teacher;

            $teacherID = (string) ($teacher['id'] ?? '');
            if ($teacherID !== '') {
                if ($observer = $this->linkedTeacherRole('observer', $teacherID)) {
                    $roles['observer'] = $observer + ['teacher' => $teacher];
                }
                if ($external = $this->linkedTeacherRole('external_observer', $teacherID)) {
                    $roles['external_observer'] = $external + ['teacher' => $teacher];
                }
            }
        }

        if ($identity = $this->identityFromSource('guru_new', $email)) {
            $roles['new_teacher'] = $identity;
        }

        if ($identity = $this->identityFromSource('principal', $email)) {
            $roles['principal'] = $identity;
        }

        if ($identity = $this->identityFromSource('hr_administrator', $email)) {
            $roles['hr_administrator'] = $identity;
        }

        if ($identity = $this->identityFromSource('trainer', $email)) {
            $roles['trainer'] = $identity;
        }

        if ($identity = $this->outsiderIdentity($email)) {
            $roles['outsider'] = $identity;
        }

        return $roles;
    }

    private function identityFromSource(string $sourceKey, string $email): ?array
    {
        $source = (array) config("unified_access.credential_sources.{$sourceKey}", []);
        if (!$source) {
            return null;
        }

        $row = $this->findByEmail($source, $email);
        if (!$row || !$this->sourceIsActive($source, $row)) {
            return null;
        }

        return $this->identityPayload($sourceKey, $source, $row);
    }

    private function outsiderIdentity(string $email): ?array
    {
        if (!Schema::hasTable('outsider')) {
            return null;
        }

        $userSource = (array) config('unified_access.credential_sources.users', []);
        $userRow = $this->findByEmail($userSource, $email);

        if ($userRow && $this->hasColumn('outsider', 'user_id')) {
            $usersTable = (string) ($userSource['table'] ?? 'users');
            $userIdColumn = $this->firstExistingColumn($usersTable, (array) ($userSource['id'] ?? ['id']));
            $userID = $userIdColumn ? ($userRow->{$userIdColumn} ?? null) : null;
            if ($userID !== null) {
                $row = DB::table('outsider')->where('user_id', $userID)->first();
                if ($row) {
                    return [
                        'source' => 'outsider',
                        'table' => 'outsider',
                        'id' => (string) ($row->outsider_id ?? ''),
                        'name' => trim((string) ($row->name ?? '')) ?: (string) ($userRow->name ?? $email),
                        'email' => $email,
                        'user_id' => (string) $userID,
                    ];
                }
            }
        }

        return null;
    }

    private function linkedTeacherRole(string $table, string $teacherID): ?array
    {
        if (!Schema::hasTable($table) || !$this->hasColumn($table, 'teacherID')) {
            return null;
        }

        $query = DB::table($table)->where('teacherID', $teacherID);
        if ($this->hasColumn($table, 'status')) {
            $query->whereRaw('LOWER(TRIM(`status`)) IN (?, ?)', ['active', 'aktif']);
        }

        $row = $query->first();
        if (!$row) {
            return null;
        }

        $idColumn = $table === 'observer' ? 'observerID' : 'externalObserverID';

        return [
            'source' => $table,
            'table' => $table,
            'id' => (string) ($row->{$idColumn} ?? ''),
            'name' => '',
            'email' => '',
            'teacherID' => $teacherID,
        ];
    }

    private function identityPayload(string $sourceKey, array $source, object $row): array
    {
        $table = (string) ($source['table'] ?? '');
        $idColumn = $this->firstExistingColumn($table, (array) ($source['id'] ?? []));
        $nameColumn = $this->firstExistingColumn($table, (array) ($source['name'] ?? []));
        $emailColumn = $this->firstExistingColumn($table, (array) ($source['email'] ?? []));

        return [
            'source' => $sourceKey,
            'table' => $table,
            'id' => $idColumn ? (string) ($row->{$idColumn} ?? '') : '',
            'name' => $nameColumn ? (string) ($row->{$nameColumn} ?? '') : '',
            'email' => $emailColumn ? strtolower((string) ($row->{$emailColumn} ?? '')) : '',
        ];
    }

    private function findByEmail(array $source, string $email): ?object
    {
        $table = (string) ($source['table'] ?? '');
        if ($table === '' || !Schema::hasTable($table)) {
            return null;
        }

        $emailColumn = $this->firstExistingColumn($table, (array) ($source['email'] ?? []));
        if (!$emailColumn) {
            return null;
        }

        return DB::table($table)
            ->whereRaw('LOWER(TRIM(`'.$emailColumn.'`)) = ?', [$email])
            ->first();
    }

    private function sourceIsActive(array $source, object $row): bool
    {
        $table = (string) ($source['table'] ?? '');

        // The canonical teacher table has no status column. Tya's current
        // standalone login treats a terminated assignment as inactive, so the
        // unified gateway mirrors that rule when an assignment exists.
        if ($table === 'teacher' && Schema::hasTable('assign')) {
            $teacherID = (string) ($row->teacherID ?? '');
            if ($teacherID !== '') {
                $latest = DB::table('assign')
                    ->where('teacherID', $teacherID)
                    ->orderByDesc('assignDate')
                    ->first();

                if ($latest && in_array(strtolower(trim((string) ($latest->status ?? ''))), ['berhenti', 'inactive', 'tidak aktif'], true)) {
                    return false;
                }
            }
        }

        $statusColumn = $this->firstExistingColumn($table, (array) ($source['status'] ?? []));
        if (!$statusColumn) {
            return true;
        }

        $value = strtolower(trim((string) ($row->{$statusColumn} ?? '')));
        if ($value === '') {
            return true;
        }

        $allowed = array_map(
            static fn ($item): string => strtolower((string) $item),
            (array) ($source['active_values'] ?? ['active', 'aktif'])
        );

        return in_array($value, $allowed, true);
    }

    private function passwordMatches(string $plain, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            return password_verify($plain, $stored);
        }

        // Legacy compatibility for old group records. Successful plaintext
        // matches are upgraded to a Laravel-supported hash immediately.
        return hash_equals($stored, $plain);
    }

    private function upgradeLegacyPasswordIfNeeded(array $source, object $row, string $passwordColumn, string $stored, string $plain): void
    {
        $info = password_get_info($stored);
        if (!empty($info['algo']) && !password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            return;
        }

        $table = (string) ($source['table'] ?? '');
        $idColumn = $this->firstExistingColumn($table, (array) ($source['id'] ?? []));
        if (!$idColumn) {
            return;
        }

        $id = $row->{$idColumn} ?? null;
        if ($id === null || $id === '') {
            return;
        }

        try {
            DB::table($table)->where($idColumn, $id)->update([$passwordColumn => Hash::make($plain)]);
        } catch (\Throwable) {
            // Authentication already succeeded; a hash-upgrade failure should
            // not block the user from entering the gateway.
        }
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        if ($table === '' || !Schema::hasTable($table)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $this->hasColumn($table, (string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return in_array($column, $this->columns[$table], true);
    }
}
