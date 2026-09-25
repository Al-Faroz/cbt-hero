<?php

namespace App\Services;

use App\Models\AuthLoginAttemptModel;
use App\Models\ManagerUserModel;

class ManagerAuthService
{
    private const MAX_FAILURES = 5;
    private const LOCK_MINUTES = 10;

    private ManagerUserModel $users;
    private AuthLoginAttemptModel $attempts;

    public function __construct()
    {
        $this->users    = new ManagerUserModel();
        $this->attempts = new AuthLoginAttemptModel();
    }

    public function authenticate(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent
    ): array {
        $username = strtoupper(trim($username));

        if ($username === '' || $password === '') {
            return [
                'ok'      => false,
                'code'    => 'VALIDATION_FAILED',
                'message' => 'Username dan password wajib diisi.',
                'status'  => 422,
            ];
        }

        if (mb_strlen($username) > 64 || strlen($password) > 4096) {
            return [
                'ok'      => false,
                'code'    => 'VALIDATION_FAILED',
                'message' => 'Data login tidak valid.',
                'status'  => 422,
            ];
        }

        $user = $this->users
            ->where('username', $username)
            ->first();

        if ($user === null) {
            $this->recordAttempt(
                $username,
                null,
                $ipAddress,
                $userAgent,
                false,
                'USER_NOT_FOUND'
            );

            return $this->invalidCredentials();
        }

        $userId = (int) $user['id'];

        if (! in_array($user['role'], ['ADMIN', 'OPERATOR'], true)) {
            $this->recordAttempt(
                $username,
                $userId,
                $ipAddress,
                $userAgent,
                false,
                'INVALID_ROLE'
            );

            return $this->invalidCredentials();
        }

        if ($user['status'] !== 'ACTIVE') {
            $this->recordAttempt(
                $username,
                $userId,
                $ipAddress,
                $userAgent,
                false,
                'ACCOUNT_INACTIVE'
            );

            return [
                'ok'      => false,
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Akun Manager tidak aktif.',
                'status'  => 403,
            ];
        }

        if ($this->isLocked($user['locked_until'] ?? null)) {
            $this->recordAttempt(
                $username,
                $userId,
                $ipAddress,
                $userAgent,
                false,
                'ACCOUNT_LOCKED'
            );

            return [
                'ok'      => false,
                'code'    => 'ACCOUNT_LOCKED',
                'message' => 'Login sementara dikunci. Silakan coba kembali beberapa menit lagi.',
                'status'  => 429,
            ];
        }

        if (! empty($user['locked_until'])) {
            $this->users->update($userId, [
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);

            $user['failed_login_count'] = 0;
            $user['locked_until']       = null;
        }

        if (! password_verify($password, $user['password_hash'])) {
            $failedCount = ((int) $user['failed_login_count']) + 1;

            $update = [
                'failed_login_count' => $failedCount,
                'locked_until'       => null,
            ];

            $failureReason = 'INVALID_PASSWORD';

            if ($failedCount >= self::MAX_FAILURES) {
                $update['locked_until'] = date(
                    'Y-m-d H:i:s',
                    time() + (self::LOCK_MINUTES * 60)
                );
                $failureReason = 'MAX_FAILURE_LOCK';
            }

            $this->users->update($userId, $update);

            $this->recordAttempt(
                $username,
                $userId,
                $ipAddress,
                $userAgent,
                false,
                $failureReason
            );

            if ($failedCount >= self::MAX_FAILURES) {
                return [
                    'ok'      => false,
                    'code'    => 'ACCOUNT_LOCKED',
                    'message' => 'Login sementara dikunci. Silakan coba kembali 10 menit lagi.',
                    'status'  => 429,
                ];
            }

            return $this->invalidCredentials();
        }

        $this->users->update($userId, [
            'failed_login_count' => 0,
            'locked_until'       => null,
            'last_login_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->recordAttempt(
            $username,
            $userId,
            $ipAddress,
            $userAgent,
            true,
            null
        );

        return [
            'ok'     => true,
            'status' => 200,
            'user'   => [
                'id'       => $userId,
                'username' => $user['username'],
                'nama'     => $user['nama'],
                'role'     => $user['role'],
            ],
        ];
    }

    private function invalidCredentials(): array
    {
        return [
            'ok'      => false,
            'code'    => 'AUTH_INVALID',
            'message' => 'Username atau password tidak sesuai.',
            'status'  => 401,
        ];
    }

    private function isLocked(?string $lockedUntil): bool
    {
        if ($lockedUntil === null || $lockedUntil === '') {
            return false;
        }

        $timestamp = strtotime($lockedUntil);

        return $timestamp !== false && $timestamp > time();
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
            'realm'          => 'MANAGER',
            'username_input' => $username,
            'account_ref_id' => $accountId,
            'ip_address'     => $ipAddress,
            'user_agent'     => mb_substr($userAgent, 0, 500),
            'success'        => $success ? 1 : 0,
            'failure_reason' => $reason,
            'attempted_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
