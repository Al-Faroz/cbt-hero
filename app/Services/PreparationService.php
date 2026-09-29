<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class PreparationService
{
    private const TYPE_ORDER = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING', 'ISIAN_SINGKAT', 'URAIAN'];
    private const CHUNK_SIZE = 100;

    public function status(int $jadwalId): array
    {
        $db = Database::connect();
        $context = $this->context($db, $jadwalId);
        if ($context === null) return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');

        $targets = $this->targets($db, $context);
        $fingerprint = $this->scheduleFingerprint($db, $context);
        $summary = $this->summarize($db, $context, $targets, $fingerprint);
        $preflight = $this->participantPreflight($db, $context, array_keys($targets));
        $assignmentsReady = $summary['total'] > 0
            && $summary['ready'] === $summary['total']
            && $summary['stale'] === 0
            && $summary['failed'] === 0
            && $summary['missing'] === 0;

        return ['ok' => true, 'status' => 200, 'data' => [
            'jadwal' => $this->publicContext($context),
            'total_target' => $summary['total'],
            'ready' => $summary['ready'],
            'stale' => $summary['stale'],
            'failed' => $summary['failed'],
            'missing' => $summary['missing'],
            'progress' => $summary['total'] > 0
                ? (int) floor(($summary['ready'] / $summary['total']) * 100)
                : 0,
            'assignments_ready' => $assignmentsReady,
            'can_start' => $assignmentsReady
                && $preflight['pass']
                && $context['bank_status'] === 'READY'
                && (int) $context['durasi_seconds'] > 0
                && strtotime((string) $context['batas_mulai_at']) > strtotime((string) $context['mulai_at']),
            'preflight' => $preflight,
            'source_fingerprint' => $fingerprint,
        ]];
    }

    public function prepare(
        int $jadwalId,
        array $payload,
        string $idempotencyKey,
        array $actor,
        bool $rebuildOnly = false
    ): array {
        if (!$this->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $scope = strtoupper(trim($this->scalar($payload['scope'] ?? 'ALL')));
        $selectedIds = $payload['peserta_kegiatan_ids'] ?? [];
        $force = (bool) ($payload['force_rebuild'] ?? false);
        if (!in_array($scope, ['ALL', 'SELECTED'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Scope Preparation tidak valid.');

        if ($scope === 'SELECTED') {
            if (!is_array($selectedIds) || !$selectedIds)
                return $this->error(422, 'VALIDATION_FAILED', 'Pilih peserta untuk Preparation terpilih.');
            $selectedIds = array_values(array_unique(array_filter(array_map(
                static fn($value): int => is_scalar($value) ? (int) $value : 0,
                $selectedIds
            ), static fn(int $value): bool => $value > 0)));
            if (!$selectedIds)
                return $this->error(422, 'VALIDATION_FAILED', 'Pilih peserta yang valid.');
            sort($selectedIds);
        } else {
            $selectedIds = [];
        }

        $hashData = [
            'jadwal_id' => $jadwalId,
            'scope' => $scope,
            'peserta_kegiatan_ids' => $selectedIds,
            'force_rebuild' => $force,
            'rebuild_only' => $rebuildOnly,
        ];
        $payloadHash = hash('sha256', json_encode($hashData, JSON_UNESCAPED_SLASHES));
        $db = Database::connect();

        $db->transBegin();
        try {
            $context = $this->context($db, $jadwalId, true);
            if ($context === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');
            }
            if ($context['bank_status'] !== 'READY' || trim((string) $context['bank_fingerprint']) === '') {
                $db->transRollback();
                return $this->error(409, 'BANK_NOT_READY', 'Bank Soal harus READY sebelum Preparation.');
            }

            $operation = $db->query(
                'SELECT * FROM jadwal_operations WHERE idempotency_key = ? FOR UPDATE',
                [$idempotencyKey]
            )->getRowArray();
            if ($operation !== null) {
                if ($operation['action'] !== ($rebuildOnly ? 'REBUILD_PREPARATION' : 'PREPARE_JADWAL')
                    || (int) $operation['root_jadwal_id'] !== $this->rootId($context)
                    || $operation['payload_hash'] !== $payloadHash) {
                    $db->transRollback();
                    return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request yang berbeda.');
                }
                if ($operation['status'] === 'COMPLETED') {
                    $db->transCommit();
                    $status = $this->status($jadwalId);
                    if (!($status['ok'] ?? false)) return $status;
                    $status['data']['processed_count'] = 0;
                    $status['data']['has_more'] = !$status['data']['can_start'];
                    $status['data']['replayed'] = true;
                    return $status;
                }
            } else {
                $db->table('jadwal_operations')->insert([
                    'idempotency_key' => $idempotencyKey,
                    'action' => $rebuildOnly ? 'REBUILD_PREPARATION' : 'PREPARE_JADWAL',
                    'root_jadwal_id' => $this->rootId($context),
                    'result_jadwal_id' => $jadwalId,
                    'payload_hash' => $payloadHash,
                    'status' => 'IN_PROGRESS',
                    'created_by' => (int) ($actor['user_id'] ?? 0),
                ]);
            }

            $targets = $this->targets($db, $context);
            if ($scope === 'SELECTED') {
                $unknown = array_diff($selectedIds, array_keys($targets));
                if ($unknown) {
                    $db->transRollback();
                    return $this->error(422, 'VALIDATION_FAILED', 'Sebagian peserta bukan target aktif Jadwal ini.');
                }
                $targets = array_intersect_key($targets, array_flip($selectedIds));
            }
            if (!$targets) {
                $db->transRollback();
                return $this->error(409, 'NO_TARGET', 'Belum ada peserta target untuk Preparation.');
            }
            if ($force && count($targets) > self::CHUNK_SIZE) {
                $db->transRollback();
                return $this->error(
                    422,
                    'FORCE_REBUILD_SCOPE_TOO_LARGE',
                    'Force rebuild dibatasi maksimal ' . self::CHUNK_SIZE . ' peserta per request. Gunakan scope SELECTED atau Bangun Ulang Terdampak.'
                );
            }

            $fingerprint = $this->scheduleFingerprint($db, $context);
            $states = $this->assignmentStates($db, $context, $targets, $fingerprint);

            $queue = [];
            foreach ($targets as $pkId => $target) {
                $state = $states[$pkId]['state'] ?? 'MISSING';
                if ($rebuildOnly) {
                    if (in_array($state, ['STALE', 'FAILED'], true)) $queue[$pkId] = $target;
                } elseif ($force || in_array($state, ['MISSING', 'STALE', 'FAILED'], true)) {
                    $queue[$pkId] = $target;
                }
            }
            ksort($queue);
            $chunk = array_slice($queue, 0, self::CHUNK_SIZE, true);

            $plan = $this->buildPlan($db, $context);
            $processed = 0;
            foreach ($chunk as $pkId => $target) {
                $this->buildAssignment($db, $context, $target, $fingerprint, $plan, $actor);
                $processed++;
            }

            $db->table('jadwal_operations')->where('idempotency_key', $idempotencyKey)->update([
                'status' => 'COMPLETED',
                'finished_at' => date('Y-m-d H:i:s'),
            ]);

            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                $rebuildOnly ? 'REBUILD_PREPARATION' : 'PREPARE_JADWAL',
                'MASTER_UJIAN',
                ($rebuildOnly ? 'Rebuild' : 'Prepare') . ' ' . $processed . ' assignment Jadwal #' . $jadwalId,
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'jadwal',
                $jadwalId,
                null,
                ['processed_count' => $processed, 'scope' => $scope]
            );

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Preparation gagal.');

            $status = $this->status($jadwalId);
            if (!($status['ok'] ?? false)) return $status;
            $status['data']['processed_count'] = $processed;
            $status['data']['has_more'] = $force ? false : count($queue) > $processed;
            $status['data']['replayed'] = false;
            return $status;
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Preparation Jadwal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'PREPARATION_FAILED', 'Preparation tidak dapat diselesaikan. Periksa kesiapan Bank/Jadwal lalu jalankan kembali.');
        }
    }

    public function readyAssignmentForStart(int $jadwalId, int $pesertaKegiatanId): ?array
    {
        $db = Database::connect();
        $context = $this->context($db, $jadwalId);
        if ($context === null || $context['bank_status'] !== 'READY') return null;

        $targets = $this->targets($db, $context);
        $target = $targets[$pesertaKegiatanId] ?? null;
        if ($target === null) return null;

        $fingerprint = $this->scheduleFingerprint($db, $context);
        $expected = hash('sha256', implode('|', [
            $fingerprint,
            $pesertaKegiatanId,
            (string) ($target['target_mode'] ?? 'MAIN'),
            (string) ($target['supersede_attempt_id'] ?? ''),
        ]));

        $assignment = $db->table('prepared_assignment')
            ->where('generated_for_jadwal_id', $jadwalId)
            ->where('peserta_kegiatan_id', $pesertaKegiatanId)
            ->where('status', 'READY')
            ->orderBy('assignment_seq', 'DESC')
            ->get()->getRowArray();

        if ($assignment === null || !hash_equals((string) $assignment['fingerprint'], $expected)) return null;

        $assignment['target_mode'] = (string) ($target['target_mode'] ?? 'MAIN');
        $assignment['supersede_attempt_id'] = $target['supersede_attempt_id'] ?? null;
        return $assignment;
    }

    public function assignments(int $jadwalId): array
    {
        $db = Database::connect();
        $context = $this->context($db, $jadwalId);
        if ($context === null) return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');

        $rows = $db->table('prepared_assignment AS pa')
            ->select('pa.id, pa.peserta_kegiatan_id, pa.assignment_seq, pa.source_version, pa.fingerprint, pa.status, '
                . 'pa.generated_at, pa.used_at, pk.nomor_peserta, pk.nama_snapshot AS nama, pk.rombel_snapshot AS rombel, '
                . '(SELECT COUNT(*) FROM prepared_assignment_item pai WHERE pai.prepared_assignment_id = pa.id) AS item_count')
            ->join('peserta_kegiatan AS pk', 'pk.id = pa.peserta_kegiatan_id')
            ->where('pa.generated_for_jadwal_id', $jadwalId)
            ->orderBy('pk.rombel_snapshot', 'ASC')->orderBy('pk.nama_snapshot', 'ASC')
            ->orderBy('pa.assignment_seq', 'DESC')->get()->getResultArray();

        return ['ok' => true, 'status' => 200, 'data' => ['items' => $rows]];
    }

    public function detail(int $assignmentId): array
    {
        $db = Database::connect();
        $assignment = $db->table('prepared_assignment AS pa')
            ->select('pa.*, pk.nomor_peserta, pk.nama_snapshot AS nama, pk.rombel_snapshot AS rombel')
            ->join('peserta_kegiatan AS pk', 'pk.id = pa.peserta_kegiatan_id')
            ->where('pa.id', $assignmentId)->get()->getRowArray();
        if ($assignment === null) return $this->error(404, 'NOT_FOUND', 'Prepared Assignment tidak ditemukan.');

        $items = $db->table('prepared_assignment_item AS pai')
            ->select('pai.id, pai.sequence_no, pai.soal_id, pai.soal_revision_id, pai.option_order_json, '
                . 'pai.mapping_json, pai.metadata_json, sr.question_type, sr.max_point')
            ->join('soal_revision AS sr', 'sr.id = pai.soal_revision_id', 'left')
            ->where('pai.prepared_assignment_id', $assignmentId)
            ->orderBy('pai.sequence_no', 'ASC')->get()->getResultArray();

        $media = $db->table('prepared_assignment_media AS pam')
            ->select('pam.media_asset_id, pam.is_critical, pam.prefetch_order, ma.media_kind, ma.storage_type, ma.provider, ma.status')
            ->join('media_assets AS ma', 'ma.id = pam.media_asset_id')
            ->where('pam.prepared_assignment_id', $assignmentId)
            ->orderBy('pam.prefetch_order', 'ASC')->get()->getResultArray();

        return ['ok' => true, 'status' => 200, 'data' => [
            'assignment' => $assignment,
            'items' => $items,
            'media' => $media,
        ]];
    }

    private function buildAssignment($db, array $context, array $target, string $scheduleFingerprint, array $plan, array $actor): void
    {
        $pkId = (int) $target['peserta_kegiatan_id'];
        $participantFingerprint = hash('sha256', implode('|', [
            $scheduleFingerprint,
            $pkId,
            (string) ($target['target_mode'] ?? 'MAIN'),
            (string) ($target['supersede_attempt_id'] ?? ''),
        ]));

        $existing = $db->table('prepared_assignment')
            ->where('generated_for_jadwal_id', (int) $context['id'])
            ->where('peserta_kegiatan_id', $pkId)
            ->orderBy('assignment_seq', 'DESC')->get()->getRowArray();

        if ($existing !== null && $existing['status'] === 'READY'
            && hash_equals((string) $existing['fingerprint'], $participantFingerprint)) {
            return;
        }
        if ($existing !== null && $existing['used_at'] === null && $existing['status'] === 'READY') {
            $db->table('prepared_assignment')->where('id', (int) $existing['id'])->update(['status' => 'STALE']);
        }

        $nextSeq = $existing === null ? 1 : ((int) $existing['assignment_seq'] + 1);
        $sourceVersion = 'bank:' . (int) $context['bank_version'] . ':' . substr((string) $context['bank_fingerprint'], 0, 48);

        $db->table('prepared_assignment')->insert([
            'generated_for_jadwal_id' => (int) $context['id'],
            'peserta_kegiatan_id' => $pkId,
            'assignment_seq' => $nextSeq,
            'source_version' => $sourceVersion,
            'fingerprint' => $participantFingerprint,
            'status' => 'READY',
            'created_by' => (int) ($actor['user_id'] ?? 0),
        ]);
        $assignmentId = (int) $db->insertID();

        $sequence = 0;
        $revisionOrder = [];
        foreach (self::TYPE_ORDER as $type) {
            $config = $plan['config'][$type] ?? null;
            $selection = (int) ($plan['selection'][$type] ?? 0);
            if ($config === null || $selection < 1) continue;

            $pool = $plan['questions'][$type] ?? [];
            if (count($pool) < $selection)
                throw new RuntimeException($type . ': soal tersedia kurang dari jumlah yang diminta Jadwal.');

            if ((int) $config['shuffle_questions'] === 1) {
                usort($pool, fn(array $a, array $b): int =>
                    strcmp(
                        hash('sha256', $participantFingerprint . '|Q|' . $type . '|' . $a['soal_id']),
                        hash('sha256', $participantFingerprint . '|Q|' . $type . '|' . $b['soal_id'])
                    ));
            }
            $chosen = array_slice($pool, 0, $selection);

            foreach ($chosen as $question) {
                $sequence++;
                $revisionId = (int) $question['revision_id'];
                $revisionOrder[$revisionId] = min($revisionOrder[$revisionId] ?? PHP_INT_MAX, $sequence);
                $optionOrder = null;
                $mapping = null;

                if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
                    $options = $db->table('soal_opsi')->select('option_key, sort_order')
                        ->where('soal_revision_id', $revisionId)
                        ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
                        ->get()->getResultArray();
                    $keys = array_values(array_map(static fn(array $row): string => (string) $row['option_key'], $options));
                    if ((int) $config['shuffle_options'] === 1) {
                        usort($keys, fn(string $a, string $b): int =>
                            strcmp(
                                hash('sha256', $participantFingerprint . '|O|' . $revisionId . '|' . $a),
                                hash('sha256', $participantFingerprint . '|O|' . $revisionId . '|' . $b)
                            ));
                    }
                    $optionOrder = json_encode($keys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif ($type === 'MATCHING') {
                    $pairs = $db->table('soal_matching_pair')
                        ->select('left_key, right_key, sort_order')
                        ->where('soal_revision_id', $revisionId)
                        ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
                        ->get()->getResultArray();
                    $left = array_values(array_map(static fn(array $row): string => (string) $row['left_key'], $pairs));
                    $right = array_values(array_map(static fn(array $row): string => (string) $row['right_key'], $pairs));
                    if ((int) $config['shuffle_options'] === 1) {
                        usort($right, fn(string $a, string $b): int =>
                            strcmp(
                                hash('sha256', $participantFingerprint . '|M|' . $revisionId . '|' . $a),
                                hash('sha256', $participantFingerprint . '|M|' . $revisionId . '|' . $b)
                            ));
                    }
                    $mapping = json_encode(
                        ['left_order' => $left, 'right_order' => $right],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }

                $metadata = json_encode([
                    'question_type' => $type,
                    'weight_percent' => (float) $config['weight_percent'],
                    'max_point' => (float) $question['max_point'],
                    'shuffle_questions' => (bool) $config['shuffle_questions'],
                    'shuffle_options' => (bool) $config['shuffle_options'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $db->table('prepared_assignment_item')->insert([
                    'prepared_assignment_id' => $assignmentId,
                    'sequence_no' => $sequence,
                    'soal_id' => (int) $question['soal_id'],
                    'soal_revision_id' => $revisionId,
                    'psych_item_id' => null,
                    'psych_item_revision_id' => null,
                    'option_order_json' => $optionOrder,
                    'mapping_json' => $mapping,
                    'metadata_json' => $metadata,
                ]);
            }
        }

        if ($sequence < 1) throw new RuntimeException('Assignment tidak mempunyai soal.');

        if ($revisionOrder) {
            $media = $db->table('soal_revision_media AS srm')
                ->select('srm.soal_revision_id, srm.media_asset_id, ma.media_kind, ma.status')
                ->join('media_assets AS ma', 'ma.id = srm.media_asset_id')
                ->whereIn('srm.soal_revision_id', array_keys($revisionOrder))
                ->orderBy('srm.sort_order', 'ASC')->get()->getResultArray();
            $seen = [];
            foreach ($media as $row) {
                $mediaId = (int) $row['media_asset_id'];
                if (isset($seen[$mediaId])) continue;
                if ($row['status'] !== 'ACTIVE') throw new RuntimeException('Media assignment tidak aktif.');
                $seen[$mediaId] = true;
                $db->table('prepared_assignment_media')->insert([
                    'prepared_assignment_id' => $assignmentId,
                    'media_asset_id' => $mediaId,
                    'is_critical' => $row['media_kind'] === 'IMAGE' ? 1 : 0,
                    'prefetch_order' => (int) ($revisionOrder[(int) $row['soal_revision_id']] ?? 999999),
                ]);
            }
        }
    }

    private function buildPlan($db, array $context): array
    {
        $configRows = $db->table('bank_type_config')
            ->select('question_type, weight_percent, shuffle_questions, shuffle_options')
            ->where('bank_soal_id', (int) $context['bank_soal_id'])->get()->getResultArray();
        $config = array_column($configRows, null, 'question_type');

        $selectionRows = $db->table('jadwal_type_selection')
            ->select('question_type, selection_count')
            ->where('jadwal_id', (int) $context['id'])->get()->getResultArray();
        $selection = [];
        foreach ($selectionRows as $row) $selection[(string) $row['question_type']] = (int) $row['selection_count'];

        $questions = $db->table('soal AS s')
            ->select('s.id AS soal_id, s.sort_order, sr.id AS revision_id, sr.question_type, sr.max_point')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
            ->where('s.bank_soal_id', (int) $context['bank_soal_id'])
            ->where('s.status', 'ACTIVE')
            ->orderBy('s.sort_order', 'ASC')->orderBy('s.id', 'ASC')
            ->get()->getResultArray();
        $byType = [];
        foreach ($questions as $question) $byType[(string) $question['question_type']][] = $question;

        foreach ($selection as $type => $count) {
            if (!isset($config[$type]) || $count < 1 || count($byType[$type] ?? []) < $count)
                throw new RuntimeException('Komposisi Preparation tidak lagi sesuai dengan Bank READY.');
        }
        return ['config' => $config, 'selection' => $selection, 'questions' => $byType];
    }

    private function summarize($db, array $context, array $targets, string $fingerprint): array
    {
        $states = $this->assignmentStates($db, $context, $targets, $fingerprint);
        $summary = ['total' => count($targets), 'ready' => 0, 'stale' => 0, 'failed' => 0, 'missing' => 0];
        foreach ($targets as $pkId => $_) {
            $state = $states[$pkId]['state'] ?? 'MISSING';
            $key = strtolower($state);
            if (array_key_exists($key, $summary)) $summary[$key]++;
            else $summary['missing']++;
        }
        return $summary;
    }

    private function assignmentStates($db, array $context, array $targets, string $fingerprint): array
    {
        if (!$targets) return [];
        $rows = $db->table('prepared_assignment')
            ->select('id, peserta_kegiatan_id, assignment_seq, fingerprint, status, used_at')
            ->where('generated_for_jadwal_id', (int) $context['id'])
            ->whereIn('peserta_kegiatan_id', array_keys($targets))
            ->orderBy('assignment_seq', 'DESC')->get()->getResultArray();
        $latest = [];
        foreach ($rows as $row) {
            $pkId = (int) $row['peserta_kegiatan_id'];
            if (!isset($latest[$pkId])) $latest[$pkId] = $row;
        }

        $states = [];
        foreach ($targets as $pkId => $target) {
            $row = $latest[$pkId] ?? null;
            if ($row === null) {
                $states[$pkId] = ['state' => 'MISSING', 'assignment' => null];
                continue;
            }
            $expected = hash('sha256', implode('|', [
                $fingerprint,
                $pkId,
                (string) ($target['target_mode'] ?? 'MAIN'),
                (string) ($target['supersede_attempt_id'] ?? ''),
            ]));
            if ($row['status'] === 'READY' && hash_equals((string) $row['fingerprint'], $expected)) {
                $states[$pkId] = ['state' => 'READY', 'assignment' => $row];
            } elseif ($row['status'] === 'FAILED') {
                $states[$pkId] = ['state' => 'FAILED', 'assignment' => $row];
            } else {
                $states[$pkId] = ['state' => 'STALE', 'assignment' => $row];
            }
        }
        return $states;
    }

    private function targets($db, array $context): array
    {
        if ($context['jenis_jadwal'] === 'SUSULAN') {
            $rows = $db->table('jadwal_peserta_target AS t')
                ->select('t.peserta_kegiatan_id, t.target_mode, t.supersede_attempt_id, pk.peserta_id')
                ->join('peserta_kegiatan AS pk', 'pk.id = t.peserta_kegiatan_id')
                ->join('peserta AS p', 'p.id = pk.peserta_id')
                ->where('t.jadwal_id', (int) $context['id'])
                ->where('t.status', 'TARGETED')->where('pk.status', 'ACTIVE')->where('p.status', 'ACTIVE')
                ->get()->getResultArray();
        } else {
            $rows = $db->table('peserta_kegiatan AS pk')
                ->select('pk.id AS peserta_kegiatan_id, pk.peserta_id')
                ->join('peserta AS p', 'p.id = pk.peserta_id')
                ->join('rombel AS rb', 'rb.id = p.rombel_id')
                ->where('pk.kegiatan_id', (int) $context['kegiatan_id'])
                ->where('pk.status', 'ACTIVE')->where('p.status', 'ACTIVE')
                ->where('rb.tingkat', (int) $context['tingkat'])
                ->get()->getResultArray();
            foreach ($rows as &$row) {
                $row['target_mode'] = 'MAIN';
                $row['supersede_attempt_id'] = null;
            }
            unset($row);
        }

        $result = [];
        foreach ($rows as $row) $result[(int) $row['peserta_kegiatan_id']] = $row;
        ksort($result);
        return $result;
    }

    private function participantPreflight($db, array $context, array $membershipIds): array
    {
        if (!$membershipIds) return [
            'pass' => false,
            'missing_number' => 0,
            'credential_not_ready' => 0,
            'message' => 'Belum ada peserta target.',
        ];
        $rows = $db->table('peserta_kegiatan AS pk')
            ->select('pk.id, pk.nomor_peserta, p.username, p.password_hash, p.credential_status')
            ->join('peserta AS p', 'p.id = pk.peserta_id')
            ->whereIn('pk.id', $membershipIds)->get()->getResultArray();
        $missingNumber = 0; $credential = 0;
        foreach ($rows as $row) {
            if (trim((string) $row['nomor_peserta']) === '') $missingNumber++;
            if (trim((string) $row['username']) === '' || trim((string) $row['password_hash']) === ''
                || $row['credential_status'] !== 'READY') $credential++;
        }
        return [
            'pass' => $missingNumber === 0 && $credential === 0,
            'missing_number' => $missingNumber,
            'credential_not_ready' => $credential,
            'message' => $missingNumber === 0 && $credential === 0
                ? 'Identitas login dan Nomor Peserta target siap.'
                : 'Masih ada peserta yang belum mempunyai Nomor Peserta atau kredensial login siap.',
        ];
    }

    private function scheduleFingerprint($db, array $context): string
    {
        $selection = $db->table('jadwal_type_selection')
            ->select('question_type, selection_count')
            ->where('jadwal_id', (int) $context['id'])
            ->orderBy('question_type', 'ASC')->get()->getResultArray();
        $config = $db->table('bank_type_config')
            ->select('question_type, weight_percent, shuffle_questions, shuffle_options, scoring_mode')
            ->where('bank_soal_id', (int) $context['bank_soal_id'])
            ->orderBy('question_type', 'ASC')->get()->getResultArray();

        return hash('sha256', json_encode([
            'jadwal_id' => (int) $context['id'],
            'bank_soal_id' => (int) $context['bank_soal_id'],
            'bank_version' => (int) $context['bank_version'],
            'bank_fingerprint' => (string) $context['bank_fingerprint'],
            'selection' => $selection,
            'config' => $config,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function context($db, int $jadwalId, bool $lock = false): ?array
    {
        if ($jadwalId < 1) return null;
        $sql = "SELECT j.*, k.jenis AS kegiatan_jenis, k.status AS kegiatan_status,
                       b.status AS bank_status, b.version_no AS bank_version, b.fingerprint AS bank_fingerprint,
                       b.nama_bank, b.tingkat, m.nama_mapel
                FROM jadwal j
                JOIN kegiatan k ON k.id = j.kegiatan_id
                JOIN bank_soal b ON b.id = j.bank_soal_id
                JOIN mata_pelajaran m ON m.id = b.mapel_id
                WHERE j.id = ?";
        if ($lock) $sql .= ' FOR UPDATE';
        $row = $db->query($sql, [$jadwalId])->getRowArray();
        if ($row === null || $row['kegiatan_jenis'] !== 'AKADEMIK' || $row['psych_instrument_id'] !== null
            || !in_array($row['jenis_jadwal'], ['MAIN', 'SUSULAN'], true)) return null;
        return $row;
    }

    private function publicContext(array $context): array
    {
        return [
            'id' => (int) $context['id'],
            'jenis_jadwal' => $context['jenis_jadwal'],
            'parent_jadwal_id' => $context['parent_jadwal_id'] === null ? null : (int) $context['parent_jadwal_id'],
            'kegiatan_id' => (int) $context['kegiatan_id'],
            'kegiatan_status' => $context['kegiatan_status'],
            'bank_soal_id' => (int) $context['bank_soal_id'],
            'nama_bank' => $context['nama_bank'],
            'mapel_nama' => $context['nama_mapel'],
            'tingkat' => (int) $context['tingkat'],
            'bank_status' => $context['bank_status'],
        ];
    }

    private function rootId(array $context): int
    {
        return $context['parent_jadwal_id'] === null ? (int) $context['id'] : (int) $context['parent_jadwal_id'];
    }

    private function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $key);
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
