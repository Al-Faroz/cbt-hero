<?php

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

class SusulanService
{
    public function list(int $mainId): array
    {
        $db = Database::connect();
        $main = $this->main($db, $mainId);
        if ($main === null) return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');

        $items = $db->query(
            "SELECT j.id, j.parent_jadwal_id, j.mulai_at, j.batas_mulai_at, j.durasi_seconds,
                    j.access_state, j.tampilkan_nilai_saat_selesai, j.first_attempt_started_at,
                    j.results_finalized_at,
                    (SELECT COUNT(*) FROM jadwal_peserta_target t WHERE t.jadwal_id = j.id AND t.status = 'TARGETED') AS target_count,
                    (SELECT COUNT(*) FROM attempt a WHERE a.jadwal_id = j.id) AS attempt_count,
                    (SELECT COUNT(*) FROM attempt a WHERE a.jadwal_id = j.id AND a.status = 'ACTIVE') AS active_attempt_count
             FROM jadwal j
             WHERE j.parent_jadwal_id = ? AND j.jenis_jadwal = 'SUSULAN'
             ORDER BY j.id ASC",
            [$mainId]
        )->getResultArray();

        foreach ($items as $index => &$item) {
            $item['susulan_no'] = $index + 1;
            $item['editable'] = (int) $item['attempt_count'] === 0
                && $db->table('prepared_assignment')->where('generated_for_jadwal_id', (int) $item['id'])->countAllResults() === 0;
        }
        unset($item);

        return ['ok' => true, 'status' => 200, 'data' => [
            'main' => $this->mainContext($db, $main),
            'items' => $items,
        ]];
    }

    public function find(int $susulanId): ?array
    {
        $db = Database::connect();
        $row = $db->table('jadwal AS j')
            ->select('j.*, k.nama AS kegiatan_nama, b.nama_bank, b.tingkat, m.nama_mapel AS mapel_nama')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('j.id', $susulanId)->where('j.jenis_jadwal', 'SUSULAN')
            ->get()->getRowArray();
        if ($row === null) return null;
        $row['targets'] = $this->targetRows($db, $susulanId);
        $row['editable'] = $db->table('attempt')->where('jadwal_id', $susulanId)->countAllResults() === 0
            && $db->table('prepared_assignment')->where('generated_for_jadwal_id', $susulanId)->countAllResults() === 0;
        return $row;
    }

    public function candidates(int $mainId, array $query): array
    {
        $db = Database::connect();
        $main = $this->main($db, $mainId);
        if ($main === null) return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');

        $page = min($this->positive($query['page'] ?? null, 1), 100000);
        $perPage = $this->positive($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $ignoreSusulanId = $this->positive($query['susulan_id'] ?? null, 0);
        if ($ignoreSusulanId > 0) {
            $candidateSusulan = $db->table('jadwal')->select('id, parent_jadwal_id, jenis_jadwal')
                ->where('id', $ignoreSusulanId)->get()->getRowArray();
            if ($candidateSusulan === null || $candidateSusulan['jenis_jadwal'] !== 'SUSULAN'
                || (int) $candidateSusulan['parent_jadwal_id'] !== $mainId) {
                $ignoreSusulanId = 0;
            }
        }

        $builder = $db->table('peserta_kegiatan AS pk')
            ->join('peserta AS p', 'p.id = pk.peserta_id')
            ->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->where('pk.kegiatan_id', (int) $main['kegiatan_id'])
            ->where('pk.status', 'ACTIVE')->where('p.status', 'ACTIVE');
        $total = (int) $builder->countAllResults(false);
        if ($q !== '') {
            $builder->groupStart()->like('pk.nama_snapshot', $q)->orLike('pk.rombel_snapshot', $q)
                ->orLike('pk.nomor_peserta', $q)->groupEnd();
        }
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->select('pk.id AS peserta_kegiatan_id, pk.peserta_id, pk.nomor_peserta, '
                . 'pk.nama_snapshot AS nama, pk.rombel_snapshot AS rombel, r.nama AS ruang')
            ->orderBy('pk.rombel_snapshot', 'ASC')->orderBy('pk.nama_snapshot', 'ASC')->orderBy('pk.id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        $ids = array_map(static fn(array $item): int => (int) $item['peserta_kegiatan_id'], $items);
        $attemptMap = $this->latestAttempts($db, $mainId, $ids);
        $pendingMap = $this->pendingTargets($db, $mainId, $ids, $ignoreSusulanId > 0 ? $ignoreSusulanId : null);

        foreach ($items as &$item) {
            $pkId = (int) $item['peserta_kegiatan_id'];
            $attempt = $attemptMap[$pkId] ?? null;
            $pending = $pendingMap[$pkId] ?? null;
            $item['eligible'] = false;
            $item['target_mode'] = null;
            $item['supersede_attempt_id'] = null;
            if ($pending !== null) {
                $item['reason'] = 'Sudah dipilih pada Susulan #' . $pending['jadwal_id'] . ' yang belum dimulai.';
            } elseif ($attempt === null) {
                $item['eligible'] = true;
                $item['target_mode'] = 'FIRST_ATTEMPT';
                $item['reason'] = 'Belum pernah memulai ujian ini.';
            } elseif ($attempt['status'] === 'FINISHED') {
                $item['eligible'] = true;
                $item['target_mode'] = 'REPLACEMENT';
                $item['supersede_attempt_id'] = (int) $attempt['id'];
                $item['reason'] = 'Replacement dari Attempt #' . $attempt['id'] . '.';
            } elseif ($attempt['status'] === 'ACTIVE') {
                $item['reason'] = 'Peserta masih mempunyai Attempt aktif.';
            } else {
                $item['reason'] = 'Riwayat Attempt belum dapat dibuatkan replacement.';
            }
        }
        unset($item);

        return ['ok' => true, 'status' => 200, 'data' => [
            'items' => $items,
            'pagination' => [
                'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
                'total' => $total, 'filtered' => $filtered,
            ],
        ]];
    }

    public function create(int $mainId, array $payload, string $idempotencyKey, array $actor): array
    {
        if (!$this->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $normalized = $this->normalizePayload($payload);
        if (!($normalized['ok'] ?? false)) return $normalized;
        $data = $normalized['data'];
        $hashPayload = $data;
        usort($hashPayload['targets'], static fn(array $a, array $b): int => $a['peserta_kegiatan_id'] <=> $b['peserta_kegiatan_id']);
        $payloadHash = hash('sha256', json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $db = Database::connect(); $db->transBegin();
        try {
            $main = $this->main($db, $mainId, true);
            if ($main === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');
            }

            $operation = $db->query('SELECT * FROM jadwal_operations WHERE idempotency_key = ? FOR UPDATE',
                [$idempotencyKey])->getRowArray();
            if ($operation !== null) {
                if ($operation['action'] !== 'CREATE_SUSULAN' || (int) $operation['root_jadwal_id'] !== $mainId
                    || $operation['payload_hash'] !== $payloadHash) {
                    $db->transRollback(); return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request yang berbeda.');
                }
                $existingId = (int) ($operation['result_jadwal_id'] ?? 0);
                $existing = $existingId > 0 ? $this->find($existingId) : null;
                if ($existing !== null) {
                    $db->transCommit();
                    return ['ok' => true, 'status' => 200, 'item' => $existing, 'replayed' => true];
                }
            } else {
                $db->table('jadwal_operations')->insert([
                    'idempotency_key' => $idempotencyKey,
                    'action' => 'CREATE_SUSULAN',
                    'root_jadwal_id' => $mainId,
                    'payload_hash' => $payloadHash,
                    'status' => 'IN_PROGRESS',
                    'created_by' => (int) ($actor['user_id'] ?? 0),
                ]);
            }

            $validated = $this->validateTargets($db, $main, $data['targets']);
            if (!($validated['ok'] ?? false)) {
                $db->transRollback(); return $validated;
            }

            $db->table('jadwal')->insert([
                'kegiatan_id' => (int) $main['kegiatan_id'],
                'bank_soal_id' => (int) $main['bank_soal_id'],
                'psych_instrument_id' => null,
                'parent_jadwal_id' => $mainId,
                'jenis_jadwal' => 'SUSULAN',
                'urutan_ujian' => max(1, (int) ($main['urutan_ujian'] ?? 1)),
                'mulai_at' => $data['mulai_at'],
                'batas_mulai_at' => $data['batas_mulai_at'],
                'durasi_seconds' => $data['durasi_seconds'],
                'access_state' => $data['access_state'],
                'tampilkan_nilai_saat_selesai' => (int) $main['tampilkan_nilai_saat_selesai'],
                'created_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            $susulanId = (int) $db->insertID();

            $selection = $db->table('jadwal_type_selection')
                ->where('jadwal_id', $mainId)->get()->getResultArray();
            foreach ($selection as $row) {
                $db->table('jadwal_type_selection')->insert([
                    'jadwal_id' => $susulanId,
                    'question_type' => $row['question_type'],
                    'selection_count' => $row['selection_count'],
                ]);
            }
            foreach ($validated['targets'] as $target) {
                $db->table('jadwal_peserta_target')->insert([
                    'jadwal_id' => $susulanId,
                    'peserta_kegiatan_id' => $target['peserta_kegiatan_id'],
                    'target_mode' => $target['target_mode'],
                    'supersede_attempt_id' => $target['supersede_attempt_id'],
                    'status' => 'TARGETED',
                    'created_by' => (int) ($actor['user_id'] ?? 0),
                ]);
            }

            $db->table('jadwal_operations')->where('idempotency_key', $idempotencyKey)->update([
                'result_jadwal_id' => $susulanId,
                'status' => 'COMPLETED',
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            $after = $this->find($susulanId);
            $this->audit($susulanId, 'CREATE_SUSULAN', null, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Susulan gagal.');
            return ['ok' => true, 'status' => 201, 'item' => $after, 'replayed' => false];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Buat Susulan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Susulan tidak dapat dibuat.');
        }
    }

    public function update(int $susulanId, array $payload, array $actor): array
    {
        $normalized = $this->normalizePayload($payload);
        if (!($normalized['ok'] ?? false)) return $normalized;
        $data = $normalized['data'];

        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$susulanId])->getRowArray();
            if ($old === null || $old['jenis_jadwal'] !== 'SUSULAN' || $old['parent_jadwal_id'] === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal Susulan tidak ditemukan.');
            }
            if ($db->table('attempt')->where('jadwal_id', $susulanId)->countAllResults() > 0
                || $db->table('prepared_assignment')->where('generated_for_jadwal_id', $susulanId)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Susulan yang sudah dipersiapkan atau dimulai tidak dapat diubah secara struktural.');
            }
            $main = $this->main($db, (int) $old['parent_jadwal_id'], true);
            if ($main === null) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Jadwal utama Susulan tidak tersedia.');
            }
            $validated = $this->validateTargets($db, $main, $data['targets'], $susulanId);
            if (!($validated['ok'] ?? false)) {
                $db->transRollback(); return $validated;
            }

            $before = $this->find($susulanId);
            $db->table('jadwal')->where('id', $susulanId)->update([
                'mulai_at' => $data['mulai_at'],
                'batas_mulai_at' => $data['batas_mulai_at'],
                'durasi_seconds' => $data['durasi_seconds'],
                'access_state' => $data['access_state'],
            ]);
            $db->table('jadwal_peserta_target')->where('jadwal_id', $susulanId)->delete();
            foreach ($validated['targets'] as $target) {
                $db->table('jadwal_peserta_target')->insert([
                    'jadwal_id' => $susulanId,
                    'peserta_kegiatan_id' => $target['peserta_kegiatan_id'],
                    'target_mode' => $target['target_mode'],
                    'supersede_attempt_id' => $target['supersede_attempt_id'],
                    'status' => 'TARGETED',
                    'created_by' => (int) ($actor['user_id'] ?? 0),
                ]);
            }
            $after = $this->find($susulanId);
            $this->audit($susulanId, 'UPDATE_SUSULAN', $before, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit perubahan Susulan gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Update Susulan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Susulan tidak dapat diperbarui.');
        }
    }

    public function cancelTarget(int $susulanId, int $targetId, array $actor): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $target = $db->query(
                'SELECT t.*, j.parent_jadwal_id, j.jenis_jadwal FROM jadwal_peserta_target t JOIN jadwal j ON j.id = t.jadwal_id WHERE t.id = ? AND t.jadwal_id = ? FOR UPDATE',
                [$targetId, $susulanId]
            )->getRowArray();
            if ($target === null || $target['jenis_jadwal'] !== 'SUSULAN') {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Target Susulan tidak ditemukan.');
            }
            if ($target['status'] === 'CANCELED') {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'item' => $target];
            }
            if ($db->table('attempt')->where('jadwal_id', $susulanId)
                ->where('peserta_kegiatan_id', (int) $target['peserta_kegiatan_id'])->countAllResults() > 0) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Target yang sudah pernah START tidak dapat dibatalkan.');
            }
            $before = $target;
            $db->table('jadwal_peserta_target')->where('id', $targetId)->update(['status' => 'CANCELED']);
            $db->table('prepared_assignment')->where('generated_for_jadwal_id', $susulanId)
                ->where('peserta_kegiatan_id', (int) $target['peserta_kegiatan_id'])
                ->where('used_at', null)->update(['status' => 'CANCELED']);
            $after = $db->table('jadwal_peserta_target')->where('id', $targetId)->get()->getRowArray();
            $this->audit($susulanId, 'CANCEL_SUSULAN_TARGET', $before, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit pembatalan target gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Batal target Susulan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Target Susulan tidak dapat dibatalkan.');
        }
    }

    private function normalizePayload(array $payload): array
    {
        $mulai = $this->dateTime($payload['mulai_at'] ?? null);
        $batas = $this->dateTime($payload['batas_mulai_at'] ?? null);
        $duration = $this->positive($payload['durasi_seconds'] ?? null, 0);
        $access = strtoupper(trim($this->scalar($payload['access_state'] ?? 'TAHAN')));
        $targets = $payload['targets'] ?? null;
        $fields = [];
        if ($mulai === null) $fields['mulai_at'] = 'Isi waktu Mulai yang valid.';
        if ($batas === null) $fields['batas_mulai_at'] = 'Isi Batas Mulai yang valid.';
        if ($mulai !== null && $batas !== null && $this->timestamp($batas) <= $this->timestamp($mulai))
            $fields['batas_mulai_at'] = 'Batas Mulai harus setelah waktu Mulai.';
        if ($duration < 1) $fields['durasi_seconds'] = 'Durasi Susulan harus lebih dari 0.';
        if (!in_array($access, ['BUKA', 'TAHAN'], true)) $fields['access_state'] = 'Pilih BUKA atau TAHAN.';
        if (!is_array($targets) || !$targets) $fields['targets'] = 'Pilih sedikitnya satu peserta Susulan.';
        if ($fields) return $this->error(422, 'VALIDATION_FAILED', 'Data Susulan belum valid.', $fields);

        $normalizedTargets = [];
        foreach ($targets as $row) {
            if (!is_array($row)) return $this->error(422, 'VALIDATION_FAILED', 'Target peserta tidak valid.');
            $pkId = $this->positive($row['peserta_kegiatan_id'] ?? null, 0);
            $mode = strtoupper(trim($this->scalar($row['target_mode'] ?? null)));
            $supersede = $this->positive($row['supersede_attempt_id'] ?? null, 0);
            if ($pkId < 1 || !in_array($mode, ['FIRST_ATTEMPT', 'REPLACEMENT'], true))
                return $this->error(422, 'VALIDATION_FAILED', 'Target peserta tidak valid.');
            if ($mode === 'FIRST_ATTEMPT') $supersede = 0;
            if ($mode === 'REPLACEMENT' && $supersede < 1)
                return $this->error(422, 'VALIDATION_FAILED', 'Replacement harus menunjuk Attempt yang diganti.');
            if (isset($normalizedTargets[$pkId]))
                return $this->error(422, 'VALIDATION_FAILED', 'Peserta yang sama dipilih lebih dari sekali.');
            $normalizedTargets[$pkId] = [
                'peserta_kegiatan_id' => $pkId,
                'target_mode' => $mode,
                'supersede_attempt_id' => $supersede > 0 ? $supersede : null,
            ];
        }
        return ['ok' => true, 'status' => 200, 'data' => [
            'mulai_at' => $mulai,
            'batas_mulai_at' => $batas,
            'durasi_seconds' => $duration,
            'access_state' => $access,
            'targets' => array_values($normalizedTargets),
        ]];
    }

    private function validateTargets($db, array $main, array $targets, ?int $ignoreSusulanId = null): array
    {
        $ids = array_column($targets, 'peserta_kegiatan_id');
        $members = $db->table('peserta_kegiatan AS pk')->select('pk.id, pk.peserta_id, pk.status, p.status AS peserta_status')
            ->join('peserta AS p', 'p.id = pk.peserta_id')
            ->where('pk.kegiatan_id', (int) $main['kegiatan_id'])->whereIn('pk.id', $ids)
            ->get()->getResultArray();
        $memberMap = array_column($members, null, 'id');
        $latest = $this->latestAttempts($db, (int) $main['id'], $ids);
        $pending = $this->pendingTargets($db, (int) $main['id'], $ids, $ignoreSusulanId);

        foreach ($targets as $target) {
            $pkId = (int) $target['peserta_kegiatan_id'];
            $member = $memberMap[$pkId] ?? null;
            if ($member === null || $member['status'] !== 'ACTIVE' || $member['peserta_status'] !== 'ACTIVE')
                return $this->error(422, 'VALIDATION_FAILED', 'Peserta target tidak aktif pada Kegiatan ini.');
            if (isset($pending[$pkId]))
                return $this->error(409, 'TARGET_ALREADY_PENDING', 'Peserta sudah mempunyai target Susulan lain yang belum dimulai.');

            $attempt = $latest[$pkId] ?? null;
            if ($target['target_mode'] === 'FIRST_ATTEMPT') {
                if ($attempt !== null)
                    return $this->error(422, 'VALIDATION_FAILED', 'FIRST_ATTEMPT hanya untuk peserta yang belum pernah START pada Jadwal utama ini.');
            } else {
                if ($attempt === null || $attempt['status'] !== 'FINISHED'
                    || (int) $attempt['id'] !== (int) $target['supersede_attempt_id']) {
                    return $this->error(422, 'VALIDATION_FAILED', 'Replacement harus menunjuk Attempt FINISHED terbaru milik peserta.');
                }
            }
        }
        return ['ok' => true, 'targets' => $targets];
    }

    private function main($db, int $mainId, bool $lock = false): ?array
    {
        if ($mainId < 1) return null;
        if ($lock) {
            $row = $db->query(
                "SELECT j.*, k.status AS kegiatan_status, k.jenis AS kegiatan_jenis,
                        b.status AS bank_status, b.nama_bank, b.tingkat, m.nama_mapel
                 FROM jadwal j
                 JOIN kegiatan k ON k.id = j.kegiatan_id
                 JOIN bank_soal b ON b.id = j.bank_soal_id
                 JOIN mata_pelajaran m ON m.id = b.mapel_id
                 WHERE j.id = ? FOR UPDATE",
                [$mainId]
            )->getRowArray();
        } else {
            $row = $db->table('jadwal AS j')
                ->select('j.*, k.status AS kegiatan_status, k.jenis AS kegiatan_jenis, '
                    . 'b.status AS bank_status, b.nama_bank, b.tingkat, m.nama_mapel')
                ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
                ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
                ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
                ->where('j.id', $mainId)->get()->getRowArray();
        }
        if ($row === null || $row['jenis_jadwal'] !== 'MAIN' || $row['parent_jadwal_id'] !== null
            || $row['kegiatan_jenis'] !== 'AKADEMIK' || $row['psych_instrument_id'] !== null) return null;
        return $row;
    }

    private function mainContext($db, array $main): array
    {
        $selection = $db->table('jadwal_type_selection')->select('question_type, selection_count')
            ->where('jadwal_id', (int) $main['id'])->orderBy('id', 'ASC')->get()->getResultArray();
        $labels = [
            'PG' => 'Pilihan Ganda',
            'PG_KOMPLEKS' => 'PG Kompleks',
            'PG_BERTINGKAT' => 'PG Bertingkat',
            'MATCHING' => 'Menjodohkan',
            'ISIAN_SINGKAT' => 'Isian Singkat',
            'URAIAN' => 'Uraian',
        ];
        foreach ($selection as &$row) {
            $row['selection_count'] = (int) $row['selection_count'];
            $row['label'] = $labels[$row['question_type']] ?? $row['question_type'];
        }
        unset($row);
        return [
            'id' => (int) $main['id'],
            'kegiatan_id' => (int) $main['kegiatan_id'],
            'kegiatan_status' => $main['kegiatan_status'],
            'bank_soal_id' => (int) $main['bank_soal_id'],
            'nama_bank' => $main['nama_bank'],
            'mapel_nama' => $main['nama_mapel'],
            'tingkat' => (int) $main['tingkat'],
            'mulai_at' => $main['mulai_at'],
            'batas_mulai_at' => $main['batas_mulai_at'],
            'durasi_seconds' => (int) $main['durasi_seconds'],
            'type_selection' => $selection,
        ];
    }

    private function targetRows($db, int $susulanId): array
    {
        return $db->table('jadwal_peserta_target AS t')
            ->select('t.id, t.peserta_kegiatan_id, t.target_mode, t.supersede_attempt_id, t.status, '
                . 'pk.nomor_peserta, pk.nama_snapshot AS nama, pk.rombel_snapshot AS rombel, r.nama AS ruang')
            ->join('peserta_kegiatan AS pk', 'pk.id = t.peserta_kegiatan_id')
            ->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->where('t.jadwal_id', $susulanId)
            ->orderBy('pk.rombel_snapshot', 'ASC')->orderBy('pk.nama_snapshot', 'ASC')
            ->get()->getResultArray();
    }

    private function latestAttempts($db, int $mainId, array $membershipIds): array
    {
        if (!$membershipIds) return [];
        $rows = $db->table('attempt')->select('id, peserta_kegiatan_id, jadwal_id, status, finish_at')
            ->where('root_jadwal_id', $mainId)->whereIn('peserta_kegiatan_id', $membershipIds)
            ->orderBy('id', 'DESC')->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $pkId = (int) $row['peserta_kegiatan_id'];
            if (!isset($map[$pkId])) $map[$pkId] = $row;
        }
        return $map;
    }

    private function pendingTargets($db, int $mainId, array $membershipIds, ?int $ignoreSusulanId = null): array
    {
        if (!$membershipIds) return [];
        $builder = $db->table('jadwal_peserta_target AS t')
            ->select('t.peserta_kegiatan_id, t.jadwal_id')
            ->join('jadwal AS j', 'j.id = t.jadwal_id')
            ->join('attempt AS a', 'a.jadwal_id = t.jadwal_id AND a.peserta_kegiatan_id = t.peserta_kegiatan_id', 'left')
            ->where('j.parent_jadwal_id', $mainId)->where('j.jenis_jadwal', 'SUSULAN')
            ->where('t.status', 'TARGETED')->where('a.id', null)
            ->whereIn('t.peserta_kegiatan_id', $membershipIds);
        if ($ignoreSusulanId !== null) $builder->where('t.jadwal_id !=', $ignoreSusulanId);
        $rows = $builder->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) $map[(int) $row['peserta_kegiatan_id']] = $row;
        return $map;
    }

    private function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $key);
    }

    private function dateTime(mixed $value): ?string
    {
        $text = trim($this->scalar($value));
        if ($text === '') return null;
        $text = str_replace('T', ' ', $text);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $text)) $text .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $text)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text, $this->timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return null;
        return $date->format('Y-m-d H:i:s');
    }

    private function timestamp(string $value): int
    {
        return (new DateTimeImmutable($value, $this->timezone()))->getTimestamp();
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('App')->appTimezone);
    }

    private function positive(mixed $value, int $default): int
    {
        $parsed = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        return is_int($parsed) ? $parsed : $default;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action, 'MASTER_UJIAN', $action . ' Jadwal #' . $id,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'jadwal', $id, $before, $after);
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
