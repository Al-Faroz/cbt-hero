<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtoupper(trim((string) env('seed.admin.username', 'ADMIN')));
        $nama = trim((string) env('seed.admin.name', 'Administrator CBT-HERO'));
        $password = (string) env('seed.admin.password');

        if ($password === '') {
            throw new RuntimeException(
                'seed.admin.password belum diisi di file .env.'
            );
        }

        if (strlen($password) < 10) {
            throw new RuntimeException(
                'Password Admin awal minimal 10 karakter.'
            );
        }

        $builder = $this->db->table('manager_users');

        $existing = $builder
            ->where('username', $username)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            echo "Admin {$username} sudah ada. Seeder dilewati." . PHP_EOL;

            return;
        }

        $builder->insert([
            'username' => $username,
            'nama' => $nama,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => null,
        ]);

        echo "Admin awal {$username} berhasil dibuat." . PHP_EOL;
    }
}
