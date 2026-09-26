<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class PesertaImportService
{
    private const TYPE = 'PESERTA';

    public function upload($file, int $actorId): array
    {
        if ($file === null || ! $file->isValid()) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih berkas XLSX yang valid.');
        }
        $name = basename((string) $file->getClientName());
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx'
            || $file->getSize() > 5 * 1024 * 1024) {
            return $this->error(422, 'VALIDATION_FAILED', 'Hanya XLSX maksimal 5 MB.');
        }
        $dir = WRITEPATH . 'uploads/imports/';
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return $this->error(503, 'IMPORT_UNAVAILABLE', 'Folder impor tidak tersedia.');
        }
        $stored = bin2hex(random_bytes(16)) . '.xlsx';
        try {
            $file->move($dir, $stored);
            $db = Database::connect();
            $db->table('import_jobs')->insert([
                'import_type' => self::TYPE,
                'original_filename' => mb_substr($name, 0, 255),
                'stored_filename' => $stored,
                'status' => 'UPLOADED',
                'created_by' => $actorId,
            ]);
            return $this->job((int) $db->insertID());
        } catch (Throwable $e) {
            @unlink($dir . $stored);
            log_message('error', 'Upload impor Peserta gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(503, 'IMPORT_UNAVAILABLE', 'Berkas tidak dapat disimpan.');
        }
    }

    public function job(int $id): array
    {
        $row = $this->rawJob($id);
        if ($row === null) {
            return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
        }
        unset($row['stored_filename']);
        return ['ok' => true, 'status' => 200, 'job' => $row];
    }

    public function items(int $id, array $query): array
    {
        if ($this->rawJob($id) === null) {
            return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
        }
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = min($this->positiveInt($query['per_page'] ?? null, 50), 100);
        $status = is_string($query['status'] ?? null) ? strtoupper($query['status']) : '';
        $db = Database::connect();
        $builder = $db->table('import_staging_items')->where('import_job_id', $id);
        if (in_array($status, ['VALID', 'INVALID', 'EXCLUDED'], true)) {
            $builder->where('validation_status', $status);
        }
        $total = $builder->countAllResults(false);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $builder->orderBy('item_no', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        foreach ($rows as &$row) {
            $payload = json_decode((string) $row['payload_json'], true) ?: [];
            $row['password_set'] = ! empty($payload['password_cipher']);
            unset($payload['password_cipher']);
            $row['payload'] = $payload;
            $row['errors'] = json_decode((string) ($row['errors_json'] ?? ''), true) ?: [];
            unset($row['payload_json'], $row['errors_json']);
        }
        return ['ok' => true, 'status' => 200, 'items' => $rows, 'pagination' => [
            'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'total' => $total,
        ]];
    }

    public function parse(int $id): array
    {
        $job = $this->rawJob($id);
        if ($job === null) {
            return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
        }
        if ($job['status'] !== 'UPLOADED' || empty($job['stored_filename'])) {
            return $this->error(409, 'STATE_CONFLICT', 'Job tidak berada pada tahap upload.');
        }
        $path = WRITEPATH . 'uploads/imports/' . basename((string) $job['stored_filename']);
        try {
            $rows = (new PesertaXlsxService())->parse($path);
            $crypto = new CredentialCryptoService();
            if (array_filter($rows, static fn ($r) => $r['data']['password'] !== '')) {
                $crypto->ensureKey();
            }
            $db = Database::connect();
            $db->transBegin();
            $locked = $db->query('SELECT status FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if (($locked['status'] ?? '') !== 'UPLOADED') {
                throw new RuntimeException('Job sudah diproses request lain.');
            }
            foreach ($rows as $row) {
                $data = $row['data'];
                $password = $data['password'];
                unset($data['password']);
                $data['password_cipher'] = $password === '' ? null : $crypto->encryptPrintablePassword($password);
                $db->table('import_staging_items')->insert([
                    'import_job_id' => $id,
                    'item_no' => $row['line'],
                    'source_ref' => 'Sheet 1 baris ' . $row['line'],
                    'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'validation_status' => 'INVALID',
                ]);
            }
            $db->table('import_jobs')->where('id', $id)->update([
                'status' => 'PARSED', 'total_items' => count($rows),
                'stored_filename' => null,
            ]);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Parse tidak dapat disimpan.');
            }
            @unlink($path);
            return $this->job($id);
        } catch (Throwable $e) {
            if (isset($db)) {
                $db->transRollback();
            }
            @unlink($path);
            Database::connect()->table('import_jobs')->where('id', $id)
                ->where('status', 'UPLOADED')->update([
                    'status' => 'FAILED', 'stored_filename' => null,
                ]);
            log_message('error', 'Parse impor Peserta {id} gagal: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return $this->error(422, 'IMPORT_INVALID', 'XLSX tidak dapat diparsing. Periksa template dan batas ukuran.');
        }
    }

    public function validate(int $id): array
    {
        $job = $this->rawJob($id);
        if ($job === null) {
            return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
        }
        if (! in_array($job['status'], ['PARSED', 'VALIDATED'], true)) {
            return $this->error(409, 'STATE_CONFLICT', 'Parse berkas sebelum validasi.');
        }
        $db = Database::connect();
        $db->transBegin();
        $lockedJob = $db->query('SELECT status FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
        if (! in_array($lockedJob['status'] ?? '', ['PARSED', 'VALIDATED'], true)) {
            $db->transRollback();
            return $this->error(409, 'STATE_CONFLICT', 'Job sudah diproses request lain.');
        }
        $rows = $db->table('import_staging_items')->where('import_job_id', $id)
            ->orderBy('item_no', 'ASC')->get()->getResultArray();
        $existingNisn = array_fill_keys(array_column($db->table('peserta')->select('nisn')->get()->getResultArray(), 'nisn'), true);
        $existingUsername = [];
        foreach ($db->table('peserta')->select('username')->where('username IS NOT NULL', null, false)->get()->getResultArray() as $user) {
            $existingUsername[strtoupper($user['username'])] = true;
        }
        $rombel = [];
        foreach ($db->table('rombel')->select('id, display_name, status')->get()->getResultArray() as $class) {
            $rombel[strtoupper($class['display_name'])] = $class;
        }
        $seenNisn = [];
        $seenUsername = [];
        $crypto = new CredentialCryptoService();
        $valid = 0;
        $invalid = 0;
        try {
            foreach ($rows as $row) {
                if ($row['validation_status'] === 'EXCLUDED') {
                    continue;
                }
                $data = json_decode((string) $row['payload_json'], true) ?: [];
                $errors = $this->validateData($data, $existingNisn, $existingUsername, $rombel, $seenNisn, $seenUsername, $crypto);
                $isValid = $errors === [];
                $db->table('import_staging_items')->where('id', $row['id'])->update([
                    'validation_status' => $isValid ? 'VALID' : 'INVALID',
                    'errors_json' => $isValid ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
                ]);
                $isValid ? $valid++ : $invalid++;
            }
            $db->table('import_jobs')->where('id', $id)->update([
                'status' => 'VALIDATED', 'valid_items' => $valid,
                'invalid_items' => $invalid, 'validated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Validasi tidak dapat disimpan.');
            }
            return $this->job($id);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Validasi impor {id} gagal: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Validasi gagal.');
        }
    }

    public function changeItem(int $jobId, int $itemId, string $action, array $changes): array
    {
        $job = $this->rawJob($jobId);
        if ($job === null) {
            return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
        }
        if (! in_array($job['status'], ['PARSED', 'VALIDATED'], true)) {
            return $this->error(409, 'STATE_CONFLICT', 'Staging sudah terkunci.');
        }
        $db = Database::connect();
        $db->transBegin();
        $lockedJob = $db->query('SELECT status FROM import_jobs WHERE id = ? FOR UPDATE', [$jobId])->getRowArray();
        if (! in_array($lockedJob['status'] ?? '', ['PARSED', 'VALIDATED'], true)) {
            $db->transRollback();
            return $this->error(409, 'STATE_CONFLICT', 'Job sudah diproses request lain.');
        }
        try {
        $row = $db->table('import_staging_items')->where('id', $itemId)
            ->where('import_job_id', $jobId)->get()->getRowArray();
        if ($row === null) {
            $db->transRollback();
            return $this->error(404, 'NOT_FOUND', 'Baris staging tidak ditemukan.');
        }
        $status = 'INVALID';
        $data = json_decode((string) $row['payload_json'], true) ?: [];
        if ($action === 'EXCLUDE') {
            $status = 'EXCLUDED';
        } elseif ($action === 'INCLUDE') {
            $status = 'INVALID';
        } elseif ($action === 'FIX') {
            foreach (['nisn', 'nama', 'jenis_kelamin', 'rombel', 'keterangan', 'username'] as $field) {
                if (array_key_exists($field, $changes)) {
                    if (! is_string($changes[$field])) {
                        $db->transRollback();
                        return $this->error(422, 'VALIDATION_FAILED', 'Nilai perbaikan harus teks.');
                    }
                    $data[$field] = trim($changes[$field]);
                }
            }
            if (array_key_exists('password', $changes)) {
                if (! is_string($changes['password'])) {
                    $db->transRollback();
                    return $this->error(422, 'VALIDATION_FAILED', 'Password perbaikan tidak valid.');
                }
                $data['password_cipher'] = $changes['password'] === '' ? null
                    : (new CredentialCryptoService())->encryptPrintablePassword($changes['password']);
            }
        } else {
            $db->transRollback();
            return $this->error(422, 'VALIDATION_FAILED', 'Aksi staging tidak dikenal.');
        }
        $db->table('import_staging_items')->where('id', $itemId)->update([
            'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'validation_status' => $status, 'errors_json' => null,
        ]);
        $db->table('import_jobs')->where('id', $jobId)->update(['status' => 'PARSED']);
        if ($db->transStatus() === false || $db->transCommit() === false) {
            $db->transRollback();
            return $this->error(409, 'STATE_CONFLICT', 'Perubahan staging gagal.');
        }
        return $this->job($jobId);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah staging {id} gagal: {message}', ['id' => $itemId, 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Perubahan staging gagal.');
        }
    }

    public function commit(int $id, string $key, array $actor): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Idempotency-Key wajib.');
        }
        $db = Database::connect();
        $db->transBegin();
        try {
            $job = $db->query('SELECT * FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($job === null || $job['import_type'] !== self::TYPE) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Job impor tidak ditemukan.');
            }
            if ($job['status'] === 'COMMITTED') {
                $db->transRollback();
                return ['ok' => true, 'status' => 200, 'job' => $this->job($id)['job'], 'replayed' => true];
            }
            if ($job['status'] !== 'VALIDATED' || (int) $job['invalid_items'] > 0
                || (int) $job['valid_items'] < 1) {
                $db->transRollback();
                return $this->error(409, 'IMPORT_NOT_READY', 'Perbaiki/keluarkan baris invalid lalu validasi ulang.');
            }
            $rows = $db->table('import_staging_items')->where('import_job_id', $id)
                ->where('validation_status', 'VALID')->orderBy('item_no')->get()->getResultArray();
            if (count($rows) !== (int) $job['valid_items']) {
                throw new RuntimeException('Jumlah baris staging berubah.');
            }
            $crypto = new CredentialCryptoService();
            foreach ($rows as $row) {
                $data = json_decode((string) $row['payload_json'], true) ?: [];
                $rombel = $db->table('rombel')->select('id')->where('display_name', strtoupper($data['rombel']))
                    ->where('status', 'ACTIVE')->get()->getRowArray();
                if ($rombel === null) {
                    throw new RuntimeException('Ada Rombel yang sudah tidak aktif.');
                }
                $username = strtoupper(trim((string) ($data['username'] ?? '')));
                $cipher = $data['password_cipher'] ?? null;
                $password = $cipher ? $crypto->decryptPrintablePassword($cipher) : null;
                $db->table('peserta')->insert([
                    'nisn' => trim((string) $data['nisn']),
                    'nama' => trim((string) $data['nama']),
                    'jenis_kelamin' => strtoupper(trim((string) $data['jenis_kelamin'])),
                    'rombel_id' => (int) $rombel['id'],
                    'status' => 'ACTIVE',
                    'keterangan' => trim((string) ($data['keterangan'] ?? '')) ?: null,
                    'username' => $username === '' ? null : $username,
                    'password_hash' => $password === null ? null : password_hash($password, PASSWORD_DEFAULT),
                    'password_encrypted' => $cipher,
                    'credential_status' => $username !== ''
                        ? ($password !== null ? 'READY' : 'USERNAME_ONLY')
                        : ($password !== null ? 'PASSWORD_ONLY' : 'PENDING'),
                ]);
                $entityId = (int) $db->insertID();
                $db->table('import_staging_items')->where('id', $row['id'])->update([
                    'committed_entity_type' => 'peserta', 'committed_entity_id' => $entityId,
                ]);
            }
            $db->table('import_jobs')->where('id', $id)->update([
                'status' => 'COMMITTED', 'committed_at' => date('Y-m-d H:i:s'),
            ]);
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
                'IMPORT_PESERTA', 'MASTER_DATA', 'Commit impor ' . count($rows) . ' Peserta.',
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
                'import_jobs', $id);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit impor gagal.');
            }
            return ['ok' => true, 'status' => 200, 'job' => $this->job($id)['job']];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Commit impor {id} gagal: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Impor gagal; tidak ada Peserta yang ditambahkan.');
        }
    }

    private function validateData(array $data, array $existingNisn, array $existingUsername,
        array $rombel, array &$seenNisn, array &$seenUsername, CredentialCryptoService $crypto): array
    {
        $errors = [];
        $nisn = trim((string) ($data['nisn'] ?? ''));
        $nama = trim((string) ($data['nama'] ?? ''));
        $jk = strtoupper(trim((string) ($data['jenis_kelamin'] ?? '')));
        $class = strtoupper(trim((string) ($data['rombel'] ?? '')));
        $username = strtoupper(trim((string) ($data['username'] ?? '')));
        $keterangan = trim((string) ($data['keterangan'] ?? ''));
        if (! preg_match('/^[0-9]{1,30}$/D', $nisn)) {
            $errors['nisn'] = 'NISN harus angka maksimal 30 digit.';
        } elseif (isset($existingNisn[$nisn]) || isset($seenNisn[$nisn])) {
            $errors['nisn'] = 'NISN duplikat pada database atau berkas.';
        }
        if ($nisn !== '') {
            $seenNisn[$nisn] = true;
        }
        if ($nama === '' || mb_strlen($nama) > 180 || strpbrk($nama, '<>') !== false) {
            $errors['nama'] = 'Nama wajib 1–180 karakter tanpa HTML.';
        }
        if (! in_array($jk, ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = 'Jenis Kelamin harus L/P.';
        }
        if (! isset($rombel[$class]) || $rombel[$class]['status'] !== 'ACTIVE') {
            $errors['rombel'] = 'Rombel tidak tersedia atau tidak aktif.';
        }
        if (mb_strlen($keterangan) > 255 || strpbrk($keterangan, '<>') !== false) {
            $errors['keterangan'] = 'Keterangan maksimal 255 karakter tanpa HTML.';
        }
        if ($username !== '') {
            if (! preg_match('/^[A-Z0-9._-]{3,64}$/D', $username)) {
                $errors['username'] = 'Username tidak valid.';
            } elseif (isset($existingUsername[$username]) || isset($seenUsername[$username])) {
                $errors['username'] = 'Username duplikat pada database atau berkas.';
            }
            $seenUsername[$username] = true;
        }
        if (! empty($data['password_cipher'])) {
            try {
                $password = $crypto->decryptPrintablePassword($data['password_cipher']);
                if (strlen($password) < 8 || strlen($password) > 64) {
                    $errors['password'] = 'Password harus 8–64 karakter.';
                }
            } catch (Throwable $e) {
                $errors['password'] = 'Password staging tidak dapat dibaca.';
            }
        }
        return $errors;
    }

    private function rawJob(int $id): ?array
    {
        return $id > 0 ? Database::connect()->table('import_jobs')->where('id', $id)
            ->where('import_type', self::TYPE)->get()->getRowArray() : null;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($parsed) ? $parsed : $default;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
