<?php

namespace App\Services;

use App\Models\ParticipantModel;
use Config\Database;
use RuntimeException;
use Throwable;

class ParticipantCredentialService
{
    private const CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ2346789';
    private ParticipantModel $participants;

    public function __construct()
    {
        $this->participants = new ParticipantModel();
    }

    public function account(int $id): array
    {
        $row = $this->row($id);
        if ($row === null) {
            return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
        }
        return ['ok' => true, 'status' => 200, 'account' => $this->safeAccount($row)];
    }

    public function printable(int $id): array
    {
        $row = $this->row($id);
        if ($row === null) {
            return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
        }
        if (empty($row['password_encrypted'])) {
            return $this->error(404, 'NOT_FOUND', 'Password cetak ulang belum tersedia.');
        }
        try {
            $password = (new CredentialCryptoService())
                ->decryptPrintablePassword((string) $row['password_encrypted']);
        } catch (Throwable $e) {
            log_message('error', 'Dekripsi credential Peserta {id} gagal.', ['id' => $id]);
            return $this->error(503, 'CREDENTIAL_UNAVAILABLE', 'Password cetak ulang tidak tersedia.');
        }
        return ['ok' => true, 'status' => 200, 'printable' => [
            'username' => (string) $row['username'], 'password' => $password,
        ]];
    }

    public function username(int $id, ?string $manual, bool $replace, array $actor): array
    {
        $manual = $manual === null ? null : strtoupper(trim($manual));
        if ($manual !== null && ! preg_match('/^[A-Z0-9._-]{3,64}$/D', $manual)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Username tidak valid.', [
                'username' => 'Username 3–64 karakter huruf kapital, angka, titik, minus, atau garis bawah.',
            ]);
        }
        $db = Database::connect();
        $db->transBegin();
        try {
            $row = $this->lockedRow($id);
            if ($row === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
            }
            if ($this->isExamLocked($id)) {
                $db->transRollback();
                return $this->error(423, 'DATA_LOCKED', 'Credential terkunci selama ujian berjalan.');
            }
            if (! empty($row['username']) && ! $replace) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Peserta sudah mempunyai Username.');
            }
            $username = $manual ?? $this->randomUsername($db);
            if ($username === (string) ($row['username'] ?? '')) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Username baru sama dengan sebelumnya.');
            }
            $other = $db->table('peserta')->select('id')->where('username', $username)->get()->getRowArray();
            if ($other !== null) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Username sudah digunakan.');
            }
            $this->saveCredential($id, [
                'username' => $username,
                'credential_status' => empty($row['password_hash']) ? 'USERNAME_ONLY' : 'READY',
                'credential_revision' => ((int) $row['credential_revision']) + 1,
                'credential_changed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->audit($id, 'SET_USERNAME', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit Username gagal.');
            }
            return $this->account($id);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Username Peserta {id} gagal: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Username tidak dapat disimpan.');
        }
    }

    public function resetPassword(int $id, ?string $manual, array $actor): array
    {
        if ($manual !== null && (strlen($manual) < 8 || strlen($manual) > 64)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Password tidak valid.', [
                'password' => 'Password manual harus 8–64 karakter.',
            ]);
        }
        $db = Database::connect();
        try {
            // Key harus tersedia sebelum transaksi agar bootstrap tidak mengganggu row lock Peserta.
            $crypto = new CredentialCryptoService();
            $password = $manual ?? $this->randomPassword();
            $encrypted = $crypto->encryptPrintablePassword($password);
            $hash = password_hash($password, PASSWORD_DEFAULT);
        } catch (Throwable $e) {
            log_message('error', 'Persiapan credential gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(503, 'CREDENTIAL_UNAVAILABLE', 'Layanan credential tidak tersedia.');
        }
        $db->transBegin();
        try {
            $row = $this->lockedRow($id);
            if ($row === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
            }
            if ($this->isExamLocked($id)) {
                $db->transRollback();
                return $this->error(423, 'DATA_LOCKED', 'Credential terkunci selama ujian berjalan.');
            }
            if (empty($row['username'])) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Buat Username terlebih dahulu.');
            }
            $this->saveCredential($id, [
                'password_hash' => $hash,
                'password_encrypted' => $encrypted,
                'credential_status' => 'READY',
                'credential_revision' => ((int) $row['credential_revision']) + 1,
                'credential_changed_at' => date('Y-m-d H:i:s'),
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);
            $this->audit($id, 'RESET_PASSWORD', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit Password gagal.');
            }
            return ['ok' => true, 'status' => 200, 'account' => $this->safeAccount($this->row($id)),
                'password' => $password];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Password Peserta {id} gagal: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Password tidak dapat disimpan.');
        }
    }

    /**
     * Semua target berhasil atau tidak ada yang berubah. Response replay hanya
     * berisi jumlah, tidak menyimpan/menampilkan password plaintext massal.
     */
    public function bulk(array $payload, string $action, string $key, array $actor): array
    {
        $rawIds = $payload['ids'] ?? null;
        if (! is_array($rawIds) || count($rawIds) < 1 || count($rawIds) > 100) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih 1–100 Peserta.');
        }
        $ids = [];
        foreach ($rawIds as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($id) || in_array($id, $ids, true)) {
                return $this->error(422, 'VALIDATION_FAILED', 'ID Peserta tidak valid atau duplikat.');
            }
            $ids[] = $id;
        }
        sort($ids, SORT_NUMERIC);
        if (! in_array($action, ['GENERATE_MISSING_USERNAMES', 'REGENERATE_USERNAMES', 'RESET_PASSWORDS'], true)
            || ! preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Aksi atau Idempotency-Key tidak valid.');
        }
        $hash = hash('sha256', $action . ':' . json_encode($ids));
        $db = Database::connect();
        $existing = $db->table('credential_operations')->where('idempotency_key', $key)->get()->getRowArray();
        if ($existing !== null) {
            return $this->bulkReplay($existing, $hash);
        }
        try {
            $crypto = $action === 'RESET_PASSWORDS' ? new CredentialCryptoService() : null;
            // Inisialisasi key di luar transaksi, sebelum lock target diperoleh.
            if ($crypto !== null) {
                $crypto->ensureKey();
            }
        } catch (Throwable $e) {
            log_message('error', 'Persiapan bulk credential gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(503, 'CREDENTIAL_UNAVAILABLE', 'Layanan credential tidak tersedia.');
        }
        $db->transBegin();
        try {
            $db->table('credential_operations')->insert([
                'idempotency_key' => $key,
                'action' => $action,
                'payload_hash' => $hash,
                'created_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->query(
                'SELECT * FROM peserta WHERE id IN (' . $placeholders . ') ORDER BY id FOR UPDATE',
                $ids
            )->getResultArray();
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('Satu atau lebih Peserta tidak ditemukan.');
            }
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if ($this->isExamLocked($id)) {
                    $db->transRollback();
                    return $this->error(423, 'DATA_LOCKED', 'Ada Peserta yang terkunci oleh ujian berjalan.');
                }
            }
            $affected = 0;
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if ($action === 'GENERATE_MISSING_USERNAMES' && ! empty($row['username'])) {
                    continue;
                }
                $data = [
                    'credential_revision' => ((int) $row['credential_revision']) + 1,
                    'credential_changed_at' => date('Y-m-d H:i:s'),
                ];
                if ($action === 'RESET_PASSWORDS') {
                    if (empty($row['username'])) {
                        throw new RuntimeException('Ada Peserta tanpa Username.');
                    }
                    $password = $this->randomPassword();
                    $data += [
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'password_encrypted' => $crypto->encryptPrintablePassword($password),
                        'credential_status' => 'READY',
                        'failed_login_count' => 0,
                        'locked_until' => null,
                    ];
                } else {
                    $data += [
                        'username' => $this->randomUsername($db),
                        'credential_status' => empty($row['password_hash']) ? 'USERNAME_ONLY' : 'READY',
                    ];
                }
                $this->saveCredential($id, $data);
                $this->audit($id, $action, $actor);
                $affected++;
            }
            $db->table('credential_operations')->where('idempotency_key', $key)->update([
                'status' => 'DONE', 'affected_count' => $affected,
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit bulk gagal.');
            }
            return ['ok' => true, 'status' => 200, 'affected' => $affected, 'selected' => count($ids)];
        } catch (Throwable $e) {
            $db->transRollback();
            $raced = $db->table('credential_operations')->where('idempotency_key', $key)->get()->getRowArray();
            if ($raced !== null) {
                return $this->bulkReplay($raced, $hash);
            }
            log_message('error', 'Bulk credential gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Operasi massal tidak dapat diproses; tidak ada perubahan.');
        }
    }

    private function bulkReplay(array $existing, string $hash): array
    {
        if ($existing['payload_hash'] !== $hash) {
            return $this->error(409, 'STATE_CONFLICT', 'Idempotency-Key sudah dipakai untuk aksi berbeda.');
        }
        if ($existing['status'] !== 'DONE') {
            return $this->error(409, 'STATE_CONFLICT', 'Operasi dengan key ini masih diproses.');
        }
        return ['ok' => true, 'status' => 200, 'affected' => (int) $existing['affected_count'],
            'replayed' => true];
    }

    private function row(int $id): ?array
    {
        return $id > 0 ? $this->participants->find($id) : null;
    }

    private function lockedRow(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::connect()->query('SELECT * FROM peserta WHERE id = ? FOR UPDATE', [$id])->getRowArray();
    }

    private function isExamLocked(int $id): bool
    {
        $db = Database::connect();
        if ($db->table('peserta_kegiatan AS pk')->join('kegiatan AS k', 'k.id = pk.kegiatan_id')
            ->where('pk.peserta_id', $id)->where('k.status', 'BERJALAN')->countAllResults() > 0) {
            return true;
        }
        return $db->table('attempt_active_lock AS l')
            ->where('l.peserta_id', $id)->countAllResults() > 0;
    }

    private function saveCredential(int $id, array $data): void
    {
        if ($this->participants->update($id, $data) === false) {
            throw new RuntimeException('Update credential gagal.');
        }
    }

    private function safeAccount(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nisn' => (string) $row['nisn'],
            'nama' => (string) $row['nama'],
            'username' => $row['username'],
            'credential_status' => (string) $row['credential_status'],
            'has_password' => ! empty($row['password_hash']),
            'has_printable_password' => ! empty($row['password_encrypted']),
        ];
    }

    private function randomUsername($db): string
    {
        for ($i = 0; $i < 12; $i++) {
            $candidate = $this->randomString(8);
            if ($db->table('peserta')->where('username', $candidate)->countAllResults() === 0) {
                return $candidate;
            }
        }
        throw new RuntimeException('Tidak dapat membuat Username unik.');
    }

    private function randomString(int $length): string
    {
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= self::CHARSET[random_int(0, strlen(self::CHARSET) - 1)];
        }
        return $value;
    }

    private function randomPassword(): string
    {
        do {
            $password = $this->randomString(8);
        } while (! preg_match('/[A-Z]/', $password) || ! preg_match('/[2-9]/', $password));
        return $password;
    }

    private function audit(int $id, string $action, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_PESERTA', 'MASTER_DATA', $action . ' credential Peserta ' . $id,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'peserta', $id);
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
