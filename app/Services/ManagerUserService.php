<?php

namespace App\Services;

use App\Models\ManagerUserModel;
use Throwable;

class ManagerUserService
{
    private ManagerUserModel $users;

    public function __construct()
    {
        $this->users = new ManagerUserModel();
    }

    /**
     * Tidak pernah mengembalikan password_hash.
     *
     * @return list<array<string, mixed>>
     */
    public function listUsers(): array
    {
        return $this->users
            ->select([
                'id',
                'username',
                'nama',
                'role',
                'status',
                'failed_login_count',
                'locked_until',
                'last_login_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();
    }

    public function createUser(
        array $payload,
        int $actorId,
        string $ipAddress,
        string $userAgent
    ): array {
        $username = strtoupper(trim((string) ($payload['username'] ?? '')));
        $nama = trim((string) ($payload['nama'] ?? ''));
        $role = strtoupper(trim((string) ($payload['role'] ?? 'OPERATOR')));
        $password = (string) ($payload['password'] ?? '');

        $errors = [];

        if (
            $username === ''
            || ! preg_match('/^[A-Z0-9._-]{3,64}$/', $username)
        ) {
            $errors['username'] = 'Username 3-64 karakter: A-Z, angka, titik, garis bawah, atau minus.';
        }

        if ($nama === '' || mb_strlen($nama) > 150) {
            $errors['nama'] = 'Nama wajib diisi dan maksimal 150 karakter.';
        }

        if (! in_array($role, ['ADMIN', 'OPERATOR'], true)) {
            $errors['role'] = 'Role hanya ADMIN atau OPERATOR.';
        }

        if (strlen($password) < 10 || strlen($password) > 4096) {
            $errors['password'] = 'Password minimal 10 karakter.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Data Manager belum valid.',
                'fields' => $errors,
            ];
        }

        $exists = $this->users
            ->where('username', $username)
            ->first();

        if ($exists !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'code' => 'STATE_CONFLICT',
                'message' => 'Username Manager sudah digunakan.',
                'fields' => [
                    'username' => 'Username sudah digunakan.',
                ],
            ];
        }

        try {
            $this->users->insert([
                'username' => $username,
                'nama' => $nama,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => 'ACTIVE',
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => null,
            ]);

            $newId = (int) $this->users->getInsertID();
        } catch (Throwable $e) {
            log_message(
                'error',
                'Create Manager user gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return [
                'ok' => false,
                'status' => 409,
                'code' => 'STATE_CONFLICT',
                'message' => 'Akun Manager tidak dapat dibuat.',
                'fields' => [],
            ];
        }

        (new AuditService())->log(
            'MANAGER',
            $actorId,
            'CREATE_MANAGER_USER',
            'SYSTEM_USERS',
            "Membuat akun Manager {$username} ({$role}).",
            $ipAddress,
            $userAgent,
            'manager_users',
            $newId,
            null,
            [
                'username' => $username,
                'nama' => $nama,
                'role' => $role,
                'status' => 'ACTIVE',
            ]
        );

        return [
            'ok' => true,
            'status' => 201,
            'user' => [
                'id' => $newId,
                'username' => $username,
                'nama' => $nama,
                'role' => $role,
                'status' => 'ACTIVE',
            ],
        ];
    }
}
