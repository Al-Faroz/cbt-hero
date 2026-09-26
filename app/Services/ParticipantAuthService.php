<?php

namespace App\Services;

use App\Models\AuthLoginAttemptModel;
use App\Models\ParticipantModel;

class ParticipantAuthService
{
    private const MAX_FAILURES = 10;
    private const LOCK_MINUTES = 5;

    private ParticipantModel $participants;
    private AuthLoginAttemptModel $attempts;

    public function __construct()
    {
        $this->participants = new ParticipantModel();
        $this->attempts = new AuthLoginAttemptModel();
    }

    public function authenticate(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent
    ): array {
        $username = $this->normalizeUsername($username);

        if (
            $username === ''
            || mb_strlen($username) > 64
            || $password === ''
            || strlen($password) > 4096
        ) {
            $this->recordAttempt(
                $username,
                null,
                $ipAddress,
                $userAgent,
                false,
                'INVALID_INPUT'
            );

            return $this->invalidCredential();
        }

        $participant = $this->participants
            ->where('username', $username)
            ->first();

        if ($participant === null) {
            $this->recordAttempt(
                $username,
                null,
                $ipAddress,
                $userAgent,
                false,
                'USER_NOT_FOUND'
            );

            return $this->invalidCredential();
        }

        $participantId = (int) $participant['id'];

        if (($participant['status'] ?? '') !== 'ACTIVE') {
            $this->recordAttempt(
                $username,
                $participantId,
                $ipAddress,
                $userAgent,
                false,
                'ACCOUNT_INACTIVE'
            );

            return $this->invalidCredential();
        }

        if ($this->isLocked($participant)) {
            $this->recordAttempt(
                $username,
                $participantId,
                $ipAddress,
                $userAgent,
                false,
                'ACCOUNT_LOCKED'
            );

            return $this->locked();
        }

        if (! empty($participant['locked_until'])) {
            /*
             * Lock lama sudah lewat. Reset counter sebelum verifikasi baru.
             */
            $this->participants->update($participantId, [
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);

            $participant['failed_login_count'] = 0;
            $participant['locked_until'] = null;
        }

        $passwordHash = (string) ($participant['password_hash'] ?? '');

        if (
            $passwordHash === ''
            || ! password_verify($password, $passwordHash)
        ) {
            $failedCount = ((int) ($participant['failed_login_count'] ?? 0)) + 1;

            $update = [
                'failed_login_count' => $failedCount,
            ];

            $lockedNow = $failedCount >= self::MAX_FAILURES;

            if ($lockedNow) {
                $update['locked_until'] = date(
                    'Y-m-d H:i:s',
                    time() + (self::LOCK_MINUTES * 60)
                );
            }

            $this->participants->update($participantId, $update);

            $this->recordAttempt(
                $username,
                $participantId,
                $ipAddress,
                $userAgent,
                false,
                $lockedNow ? 'MAX_FAILURE_LOCK' : 'INVALID_PASSWORD'
            );

            return $lockedNow
                ? $this->locked()
                : $this->invalidCredential();
        }

        $this->participants->update($participantId, [
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        $this->recordAttempt(
            $username,
            $participantId,
            $ipAddress,
            $userAgent,
            true,
            null
        );

        return [
            'ok' => true,
            'participant' => [
                'id' => $participantId,
                'username' => (string) $participant['username'],
                'credential_revision' => (int) ($participant['credential_revision'] ?? 0),
            ],
        ];
    }

    public function validateSession(array $auth): ?array
    {
        if (
            ($auth['logged_in'] ?? false) !== true
            || ! isset($auth['peserta_id'])
        ) {
            return null;
        }

        $participant = $this->participants
            ->select('id, username, status, credential_revision')
            ->find((int) $auth['peserta_id']);

        if (
            ! is_array($participant)
            || ($participant['status'] ?? '') !== 'ACTIVE'
            || empty($participant['username'])
            || ! isset($auth['credential_revision'])
            || (int) $auth['credential_revision'] !== (int) $participant['credential_revision']
        ) {
            return null;
        }

        return [
            'id' => (int) $participant['id'],
            'username' => (string) $participant['username'],
        ];
    }

    public function activeAttemptId(int $participantId): ?int
    {
        $row = db_connect()
            ->table('attempt_active_lock AS aal')
            ->select('aal.attempt_id')
            ->join('attempt AS a', 'a.id = aal.attempt_id', 'inner')
            ->where('aal.peserta_id', $participantId)
            ->where('a.status', 'ACTIVE')
            ->get()
            ->getRowArray();

        if (! is_array($row) || ! isset($row['attempt_id'])) {
            return null;
        }

        return (int) $row['attempt_id'];
    }

    private function normalizeUsername(string $username): string
    {
        return mb_strtoupper(trim($username), 'UTF-8');
    }

    private function isLocked(array $participant): bool
    {
        $lockedUntil = $participant['locked_until'] ?? null;

        if (empty($lockedUntil)) {
            return false;
        }

        $timestamp = strtotime((string) $lockedUntil);

        return $timestamp !== false && $timestamp > time();
    }

    private function invalidCredential(): array
    {
        return [
            'ok' => false,
            'status' => 401,
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Username atau password tidak sesuai.',
        ];
    }

    private function locked(): array
    {
        return [
            'ok' => false,
            'status' => 429,
            'code' => 'ACCOUNT_LOCKED',
            'message' => 'Terlalu banyak percobaan login. Silakan coba kembali beberapa menit lagi.',
        ];
    }

    private function recordAttempt(
        string $username,
        ?int $accountId,
        string $ipAddress,
        string $userAgent,
        bool $success,
        ?string $reason
    ): void {
        $this->attempts->insert([
            'realm' => 'PARTICIPANT',
            'username_input' => mb_substr($username, 0, 100),
            'account_ref_id' => $accountId,
            'ip_address' => $ipAddress,
            'user_agent' => mb_substr($userAgent, 0, 500),
            'success' => $success ? 1 : 0,
            'failure_reason' => $reason,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
