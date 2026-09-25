<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class ParticipantAuthTestSeeder extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT !== 'development') {
            throw new RuntimeException(
                'ParticipantAuthTestSeeder hanya boleh dijalankan pada development.'
            );
        }

        $username = mb_strtoupper(
            trim((string) env('seed.participant.username', 'PESERTA01')),
            'UTF-8'
        );

        $password = (string) env('seed.participant.password', '');

        if ($password === '') {
            throw new RuntimeException(
                'Isi seed.participant.password di .env sebelum menjalankan seeder.'
            );
        }

        if (strlen($password) < 6) {
            throw new RuntimeException(
                'Password test Participant minimal 6 karakter.'
            );
        }

        $participant = $this->db
            ->table('peserta')
            ->where('username', $username)
            ->get()
            ->getRowArray();

        if ($participant !== null) {
            echo "Participant {$username} sudah ada. Seeder dilewati." . PHP_EOL;
            return;
        }

        $rombel = $this->db
            ->table('rombel')
            ->where('tingkat', 7)
            ->where('kode_rombel', 'TEST')
            ->get()
            ->getRowArray();

        if ($rombel === null) {
            $this->db->table('rombel')->insert([
                'tingkat' => 7,
                'kode_rombel' => 'TEST',
                'display_name' => '7-TEST',
                'status' => 'ACTIVE',
            ]);

            $rombelId = (int) $this->db->insertID();
        } else {
            $rombelId = (int) $rombel['id'];
        }

        $this->db->table('peserta')->insert([
            'nisn' => 'TEST-AUTH-001',
            'nama' => 'Peserta Test Auth',
            'jenis_kelamin' => 'L',
            'rombel_id' => $rombelId,
            'status' => 'ACTIVE',
            'keterangan' => 'Development auth test',
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_encrypted' => null,
            'credential_status' => 'READY',
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => null,
            'credential_changed_at' => date('Y-m-d H:i:s'),
        ]);

        echo "Participant test {$username} berhasil dibuat." . PHP_EOL;
    }
}
