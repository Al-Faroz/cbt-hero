<?php

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

class JadwalService
{
    private const TYPES = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING', 'ISIAN_SINGKAT', 'URAIAN'];
    private const TYPE_LABELS = [
        'PG' => 'Pilihan Ganda',
        'PG_KOMPLEKS' => 'PG Kompleks',
        'PG_BERTINGKAT' => 'PG Bertingkat',
        'MATCHING' => 'Menjodohkan',
        'ISIAN_SINGKAT' => 'Isian Singkat',
        'URAIAN' => 'Uraian',
    ];

    public function list(array $query): array
    {
        $db = Database::connect();
        $page = min($this->positive($query['page'] ?? null, 1), 100000);
        $perPage = $this->positive($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $kegiatanId = $this->positive($query['kegiatan_id'] ?? null, 0);
        $access = strtoupper(trim($this->scalar($query['access_state'] ?? null)));

        $total = (int) $db->table('jadwal')->where('jenis_jadwal', 'MAIN')
            ->where('parent_jadwal_id', null)->countAllResults();

        $builder = $db->table('jadwal AS j')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id', 'left')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id', 'left')
            ->where('j.jenis_jadwal', 'MAIN')->where('j.parent_jadwal_id', null);
        if ($q !== '') {
            $builder->groupStart()->like('k.nama', $q)->orLike('b.nama_bank', $q)
                ->orLike('m.nama_mapel', $q)->groupEnd();
        }
        if ($kegiatanId > 0) $builder->where('j.kegiatan_id', $kegiatanId);
        if (in_array($access, ['BUKA', 'TAHAN'], true)) $builder->where('j.access_state', $access);

        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->select('j.id, j.kegiatan_id, j.bank_soal_id, j.mulai_at, j.batas_mulai_at, '
                . 'j.durasi_seconds, j.access_state, j.tampilkan_nilai_saat_selesai, j.first_attempt_started_at, '
                . 'j.results_finalized_at, '
                . '(SELECT COUNT(*) FROM attempt a WHERE a.jadwal_id = j.id AND a.status = \'ACTIVE\') AS active_attempt_count, '
                . 'k.nama AS kegiatan_nama, k.status AS kegiatan_status, '
                . 'b.nama_bank, b.tingkat, b.status AS bank_status, m.nama_mapel AS mapel_nama')
            ->orderBy('j.mulai_at', 'DESC')->orderBy('j.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        $selectionMap = $this->selectionMap($db, array_map(static fn(array $item): int => (int) $item['id'], $items));
        $preparationService = new PreparationService();
        foreach ($items as &$item) {
            $id = (int) $item['id'];
            $item['type_selection'] = $selectionMap[$id] ?? [];
            $item['window_state'] = $this->windowState((string) $item['mulai_at'], (string) $item['batas_mulai_at']);
            $item['structural_editable'] = $item['kegiatan_status'] === 'DRAFT' && $item['first_attempt_started_at'] === null;
            $item['access_editable'] = $item['results_finalized_at'] === null;

            $prep = $preparationService->status($id);
            $prepData = ($prep['ok'] ?? false) ? ($prep['data'] ?? []) : [];
            $item['preparation_state'] = ($prepData['can_start'] ?? false) ? 'READY' : 'DRAFT';
            $item['preparation_progress'] = (int) ($prepData['progress'] ?? 0);
            $item['preparation_ready'] = (int) ($prepData['ready'] ?? 0);
            $item['preparation_total'] = (int) ($prepData['total_target'] ?? 0);
        }
        unset($item);

        return ['items' => $items, 'pagination' => [
            'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
            'total' => $total, 'filtered' => $filtered,
        ]];
    }

    public function options(): array
    {
        $db = Database::connect();
        $kegiatan = $db->table('kegiatan')->select('id, nama, tahun_pelajaran, semester, status')
            ->where('jenis', 'AKADEMIK')->orderBy('id', 'DESC')->get()->getResultArray();
        $banks = $db->table('bank_soal AS b')
            ->select('b.id, b.kegiatan_id, b.nama_bank, b.tingkat, b.status, b.version_no, b.fingerprint, '
                . 'k.nama AS kegiatan_nama, k.status AS kegiatan_status, m.nama_mapel AS mapel_nama')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('k.jenis', 'AKADEMIK')->where('b.status', 'READY')
            ->orderBy('k.id', 'DESC')->orderBy('m.nama_mapel', 'ASC')->orderBy('b.tingkat', 'ASC')
            ->get()->getResultArray();

        $ids = array_map(static fn(array $bank): int => (int) $bank['id'], $banks);
        $configMap = [];
        $activeMap = [];
        if ($ids) {
            $configs = $db->table('bank_type_config')->select('bank_soal_id, question_type, question_count, weight_percent, scoring_mode')
                ->whereIn('bank_soal_id', $ids)->get()->getResultArray();
            foreach ($configs as $config) $configMap[(int) $config['bank_soal_id']][] = $config;

            $counts = $db->table('soal AS s')
                ->select('s.bank_soal_id, sr.question_type, COUNT(*) AS total', false)
                ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
                ->whereIn('s.bank_soal_id', $ids)->where('s.status', 'ACTIVE')
                ->groupBy('s.bank_soal_id')->groupBy('sr.question_type')->get()->getResultArray();
            foreach ($counts as $count) $activeMap[(int) $count['bank_soal_id']][(string) $count['question_type']] = (int) $count['total'];
        }
        $rank = array_flip(self::TYPES);
        foreach ($banks as &$bank) {
            $bankId = (int) $bank['id'];
            $types = $configMap[$bankId] ?? [];
            usort($types, static fn(array $a, array $b): int => ($rank[$a['question_type']] ?? 99) <=> ($rank[$b['question_type']] ?? 99));
            $bank['types'] = array_map(function (array $config) use ($activeMap, $bankId): array {
                $type = (string) $config['question_type'];
                return [
                    'question_type' => $type,
                    'label' => self::TYPE_LABELS[$type] ?? $type,
                    'available' => (int) ($activeMap[$bankId][$type] ?? 0),
                    'planned' => (int) $config['question_count'],
                    'weight_percent' => (float) $config['weight_percent'],
                    'scoring_mode' => $config['scoring_mode'],
                ];
            }, $types);
        }
        unset($bank);

        return ['kegiatan' => $kegiatan, 'banks' => $banks];
    }

    public function find(int $id): ?array
    {
        if ($id < 1) return null;
        $db = Database::connect();
        $item = $db->table('jadwal AS j')
            ->select('j.*, '
                . '(SELECT COUNT(*) FROM attempt a WHERE a.jadwal_id = j.id AND a.status = \'ACTIVE\') AS active_attempt_count, '
                . 'k.nama AS kegiatan_nama, k.status AS kegiatan_status, b.nama_bank, b.tingkat, '
                . 'b.status AS bank_status, m.nama_mapel AS mapel_nama')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id', 'left')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id', 'left')
            ->where('j.id', $id)->where('j.jenis_jadwal', 'MAIN')->where('j.parent_jadwal_id', null)
            ->get()->getRowArray();
        if ($item === null) return null;
        $item['type_selection'] = $this->selectionMap($db, [$id])[$id] ?? [];
        $item['window_state'] = $this->windowState((string) $item['mulai_at'], (string) $item['batas_mulai_at']);
        $item['structural_editable'] = $item['kegiatan_status'] === 'DRAFT' && $item['first_attempt_started_at'] === null;
        $item['access_editable'] = $item['results_finalized_at'] === null;
        return $item;
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        $forbidden = ['id', 'parent_jadwal_id', 'jenis_jadwal', 'psych_instrument_id', 'created_by',
            'first_attempt_started_at', 'results_finalized_at', 'results_finalized_by'];
        if (array_intersect($forbidden, array_keys($payload)))
            return $this->error(422, 'VALIDATION_FAILED', 'Field Jadwal internal tidak dapat diubah melalui form.');

        $kegiatanId = $this->positive($payload['kegiatan_id'] ?? null, 0);
        $bankId = $this->positive($payload['bank_soal_id'] ?? null, 0);
        $mulai = $this->dateTime($payload['mulai_at'] ?? null);
        $batas = $this->dateTime($payload['batas_mulai_at'] ?? null);
        $duration = $this->positive($payload['durasi_seconds'] ?? null, 0);
        $access = strtoupper(trim($this->scalar($payload['access_state'] ?? null)));
        $showScore = $this->boolean($payload['tampilkan_nilai_saat_selesai'] ?? null);
        $selection = $payload['type_selection'] ?? null;
        $fields = [];

        if ($kegiatanId < 1) $fields['kegiatan_id'] = 'Pilih Kegiatan Akademik.';
        if ($bankId < 1) $fields['bank_soal_id'] = 'Pilih Bank Soal READY.';
        if ($mulai === null) $fields['mulai_at'] = 'Isi waktu Mulai yang valid.';
        if ($batas === null) $fields['batas_mulai_at'] = 'Isi Batas Mulai yang valid.';
        if ($mulai !== null && $batas !== null && $this->timestamp($batas) <= $this->timestamp($mulai))
            $fields['batas_mulai_at'] = 'Batas Mulai harus setelah waktu Mulai.';
        if ($duration < 1) $fields['durasi_seconds'] = 'Durasi harus lebih dari 0.';
        if (!in_array($access, ['BUKA', 'TAHAN'], true)) $fields['access_state'] = 'Pilih BUKA atau TAHAN.';
        if ($showScore === null) $fields['tampilkan_nilai_saat_selesai'] = 'Pilihan tampilkan nilai tidak valid.';
        if (!is_array($selection)) $fields['type_selection'] = 'Jumlah soal yang diambil per tipe wajib diisi.';
        if ($fields) return $this->error(422, 'VALIDATION_FAILED', 'Data Jadwal belum valid.', $fields);

        $db = Database::connect();
        $db->transBegin();
        try {
            $old = $id === null ? null : $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($id !== null && ($old === null || $old['jenis_jadwal'] !== 'MAIN' || $old['parent_jadwal_id'] !== null)) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');
            }
            if ($old !== null) {
                $oldActivity = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [(int) $old['kegiatan_id']])->getRowArray();
                if ($oldActivity === null || $oldActivity['status'] !== 'DRAFT' || $old['first_attempt_started_at'] !== null) {
                    $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Jadwal yang sudah berjalan tidak dapat diubah secara struktural.');
                }
            }

            $activity = $db->query('SELECT id, jenis, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($activity === null || $activity['jenis'] !== 'AKADEMIK') {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Kegiatan Akademik tidak tersedia.', ['kegiatan_id' => 'Pilih Kegiatan Akademik.']);
            }
            if ($activity['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Jadwal utama hanya dapat disusun saat Kegiatan masih DRAFT.');
            }

            $bank = $db->query('SELECT id, kegiatan_id, status, fingerprint FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            if ($bank === null || (int) $bank['kegiatan_id'] !== $kegiatanId || $bank['status'] !== 'READY') {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Pilih Bank Soal READY dari Kegiatan yang sama.', ['bank_soal_id' => 'Bank harus READY dan berasal dari Kegiatan yang dipilih.']);
            }

            $selectionResult = $this->normalizeSelection($db, $bankId, $selection);
            if (!($selectionResult['ok'] ?? false)) {
                $db->transRollback(); return $selectionResult;
            }
            $normalizedSelection = $selectionResult['selection'];

            $before = $old;
            if ($before !== null) $before['type_selection'] = $this->selectionMap($db, [(int) $id])[(int) $id] ?? [];

            $values = [
                'kegiatan_id' => $kegiatanId,
                'bank_soal_id' => $bankId,
                'psych_instrument_id' => null,
                'parent_jadwal_id' => null,
                'jenis_jadwal' => 'MAIN',
                'mulai_at' => $mulai,
                'batas_mulai_at' => $batas,
                'durasi_seconds' => $duration,
                'access_state' => $access,
                'tampilkan_nilai_saat_selesai' => $showScore ? 1 : 0,
            ];
            if ($old === null) {
                $values['created_by'] = (int) ($actor['user_id'] ?? 0);
                $db->table('jadwal')->insert($values);
                $id = (int) $db->insertID();
            } else {
                $db->table('jadwal')->where('id', $id)->update($values);
                $db->table('jadwal_type_selection')->where('jadwal_id', $id)->delete();
            }
            foreach ($normalizedSelection as $item) {
                $db->table('jadwal_type_selection')->insert([
                    'jadwal_id' => $id,
                    'question_type' => $item['question_type'],
                    'selection_count' => $item['selection_count'],
                ]);
            }

            $after = $this->find((int) $id);
            $this->audit((int) $id, $old === null ? 'CREATE' : 'UPDATE', $before, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit Jadwal gagal.');
            return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan Jadwal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Jadwal tidak dapat disimpan.');
        }
    }

    public function changeAccess(int $id, string $target, array $actor): array
    {
        $target = strtoupper(trim($target));
        if (!in_array($target, ['BUKA', 'TAHAN'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Status akses harus BUKA atau TAHAN.');
        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($old === null || $old['jenis_jadwal'] !== 'MAIN') {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');
            }
            if ($old['results_finalized_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Akses Jadwal yang hasilnya sudah difinalkan tidak dapat diubah.');
            }
            if ($old['access_state'] !== $target)
                $db->table('jadwal')->where('id', $id)->update(['access_state' => $target]);
            $after = $this->find($id);
            $this->audit($id, 'ACCESS_' . $target, $old, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit akses Jadwal gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah akses Jadwal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Akses Jadwal tidak dapat diubah.');
        }
    }

    public function delete(int $id, array $actor): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($old === null || $old['jenis_jadwal'] !== 'MAIN' || $old['parent_jadwal_id'] !== null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal utama tidak ditemukan.');
            }
            $activity = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE', [(int) $old['kegiatan_id']])->getRowArray();
            if ($activity === null || $activity['status'] !== 'DRAFT' || $old['first_attempt_started_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Hanya Jadwal yang belum berjalan pada Kegiatan DRAFT yang dapat dihapus.');
            }
            if ($db->table('jadwal')->where('parent_jadwal_id', $id)->countAllResults() > 0
                || $db->table('prepared_assignment')->where('generated_for_jadwal_id', $id)->countAllResults() > 0
                || $db->table('attempt')->where('jadwal_id', $id)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Jadwal sudah mempunyai Susulan, Preparation, atau Attempt.');
            }
            $before = $this->find($id) ?? $old;
            $db->table('jadwal')->where('id', $id)->delete();
            $this->audit($id, 'DELETE', $before, null, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit hapus Jadwal gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $before];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus Jadwal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Jadwal tidak dapat dihapus.');
        }
    }

    private function normalizeSelection($db, int $bankId, array $payload): array
    {
        $configs = $db->table('bank_type_config')->select('question_type, question_count, weight_percent')
            ->where('bank_soal_id', $bankId)->get()->getResultArray();
        if (!$configs) return $this->error(422, 'VALIDATION_FAILED', 'Komposisi Bank belum tersedia.', ['type_selection' => 'Komposisi Bank kosong.']);

        $provided = [];
        foreach ($payload as $row) {
            if (!is_array($row)) return $this->error(422, 'VALIDATION_FAILED', 'Pengambilan soal per tipe tidak valid.', ['type_selection' => 'Gunakan daftar tipe dan jumlah soal.']);
            $type = strtoupper(trim($this->scalar($row['question_type'] ?? null)));
            $count = $this->positive($row['selection_count'] ?? null, 0);
            if (!in_array($type, self::TYPES, true) || isset($provided[$type]))
                return $this->error(422, 'VALIDATION_FAILED', 'Pengambilan soal per tipe tidak valid.', ['type_selection' => 'Tipe soal duplikat atau tidak dikenal.']);
            $provided[$type] = $count;
        }

        $counts = $db->table('soal AS s')
            ->select('sr.question_type, COUNT(*) AS total', false)
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
            ->where('s.bank_soal_id', $bankId)->where('s.status', 'ACTIVE')
            ->groupBy('sr.question_type')->get()->getResultArray();
        $available = [];
        foreach ($counts as $row) $available[(string) $row['question_type']] = (int) $row['total'];

        $normalized = []; $expected = [];
        foreach ($configs as $config) {
            $type = (string) $config['question_type'];
            $expected[$type] = true;
            $count = (int) ($provided[$type] ?? 0);
            $max = (int) ($available[$type] ?? 0);
            if ($count < 1)
                return $this->error(422, 'VALIDATION_FAILED', self::TYPE_LABELS[$type] . ' wajib dipilih sedikitnya 1 soal.', ['type_selection' => self::TYPE_LABELS[$type] . ': isi jumlah soal yang diambil.']);
            if ($count > $max)
                return $this->error(422, 'VALIDATION_FAILED', self::TYPE_LABELS[$type] . ': dipilih ' . $count . ', tersedia ' . $max . '.', ['type_selection' => 'Jumlah yang diambil tidak boleh melebihi soal aktif di Bank.']);
            $normalized[] = ['question_type' => $type, 'selection_count' => $count, 'available' => $max];
        }
        foreach (array_keys($provided) as $type) {
            if (!isset($expected[$type]))
                return $this->error(422, 'VALIDATION_FAILED', 'Tipe ' . $type . ' tidak ada pada Komposisi Bank.', ['type_selection' => 'Hapus tipe yang tidak ada pada Bank.']);
        }
        return ['ok' => true, 'selection' => $normalized];
    }

    private function selectionMap($db, array $scheduleIds): array
    {
        if (!$scheduleIds) return [];
        $rows = $db->table('jadwal_type_selection')->select('jadwal_id, question_type, selection_count')
            ->whereIn('jadwal_id', $scheduleIds)->get()->getResultArray();
        $rank = array_flip(self::TYPES); $map = [];
        foreach ($rows as $row) {
            $type = (string) $row['question_type'];
            $map[(int) $row['jadwal_id']][] = [
                'question_type' => $type,
                'label' => self::TYPE_LABELS[$type] ?? $type,
                'selection_count' => (int) $row['selection_count'],
            ];
        }
        foreach ($map as &$items)
            usort($items, static fn(array $a, array $b): int => ($rank[$a['question_type']] ?? 99) <=> ($rank[$b['question_type']] ?? 99));
        unset($items);
        return $map;
    }

    private function windowState(string $start, string $latest): string
    {
        $now = time(); $startAt = $this->timestamp($start); $latestAt = $this->timestamp($latest);
        if ($now < $startAt) return 'AKAN_DATANG';
        return $now <= $latestAt ? 'JENDELA_MULAI' : 'BATAS_MULAI_LEWAT';
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

    private function boolean(mixed $value): ?bool
    {
        if (in_array($value, [true, 1, '1'], true)) return true;
        if (in_array($value, [false, 0, '0'], true)) return false;
        return null;
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_JADWAL', 'MASTER_UJIAN', $action . ' Jadwal #' . $id,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'jadwal', $id, $before, $after);
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
