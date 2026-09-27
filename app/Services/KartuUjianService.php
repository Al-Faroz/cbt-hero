<?php

namespace App\Services;

use Config\Database;
use Throwable;

class KartuUjianService
{
    public function prepare(int $kegiatanId, array $query): array
    {
        $db = Database::connect();
        $kegiatan = $db->table('kegiatan')->select('id, nama, jenis, tahun_pelajaran, semester, status')
            ->where('id', $kegiatanId)->get()->getRowArray();
        if ($kegiatan === null) return ['status' => 404, 'message' => 'Kegiatan tidak ditemukan.'];

        $scope = is_string($query['scope'] ?? null) ? $query['scope'] : 'ALL';
        $value = is_string($query['value'] ?? null) ? trim($query['value']) : '';
        if (! in_array($scope, ['ALL', 'ROMBEL', 'RUANG'], true)
            || ($scope === 'ROMBEL' && mb_strlen($value) > 50)
            || ($scope === 'RUANG' && $value !== 'NONE' && ! ctype_digit($value))) {
            return ['status' => 422, 'message' => 'Filter kartu tidak valid.'];
        }
        $options = $db->table('peserta_kegiatan')->distinct()->select('rombel_snapshot')
            ->where('kegiatan_id', $kegiatanId)->orderBy('rombel_snapshot', 'ASC')->get()->getResultArray();
        $rooms = $db->table('ruang')->select('id, kode, nama')->orderBy('kode', 'ASC')->get()->getResultArray();
        $rombel = array_column($options, 'rombel_snapshot');
        if ($scope === 'ROMBEL' && ! in_array($value, $rombel, true))
            return ['status' => 422, 'message' => 'Rombel tidak ada pada Kegiatan ini.'];
        if ($scope === 'RUANG' && $value !== 'NONE' && ! in_array($value, array_map('strval', array_column($rooms, 'id')), true))
            return ['status' => 422, 'message' => 'Ruang tidak ditemukan.'];
        if ($scope === 'ALL') $value = '';

        $filtered = static function () use ($db, $kegiatanId, $scope, $value) {
            $builder = $db->table('peserta_kegiatan AS pk')->where('pk.kegiatan_id', $kegiatanId);
            if ($scope === 'ROMBEL') $builder->where('pk.rombel_snapshot', $value);
            if ($scope === 'RUANG') {
                if ($value === 'NONE') $builder->where('pk.ruang_id IS NULL', null, false);
                else $builder->where('pk.ruang_id', (int) $value);
            }
            return $builder;
        };
        $total = (int) $filtered()->countAllResults();
        $pages = max(1, (int) ceil($total / 100));
        $pageRaw = $query['page'] ?? 1;
        $pageInput = is_scalar($pageRaw)
            ? filter_var($pageRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $page = is_int($pageInput) ? min($pageInput, $pages) : 1;
        $rows = $filtered()->select('pk.id, pk.nomor_peserta, pk.nama_snapshot, pk.rombel_snapshot, '
            . 'p.username, p.password_encrypted, r.kode AS ruang_kode, r.nama AS ruang_nama')
            ->join('peserta AS p', 'p.id = pk.peserta_id')
            ->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->orderBy('pk.rombel_snapshot', 'ASC')->orderBy('pk.nama_snapshot', 'ASC')
            ->orderBy('pk.id', 'ASC')->limit(100, ($page - 1) * 100)->get()->getResultArray();
        $crypto = null;
        $incomplete = 0;
        foreach ($rows as &$row) {
            $row['password'] = null;
            if (! empty($row['password_encrypted'])) {
                try {
                    $crypto ??= new CredentialCryptoService();
                    $row['password'] = $crypto->decryptPrintablePassword((string) $row['password_encrypted']);
                } catch (Throwable $e) {
                    log_message('error', 'Password kartu untuk anggota {id} tidak tersedia.', ['id' => $row['id']]);
                }
            }
            unset($row['password_encrypted']);
            if (empty($row['nomor_peserta']) || empty($row['username']) || $row['password'] === null
                || $row['ruang_kode'] === null) $incomplete++;
        }
        unset($row);
        return [
            'status' => 200, 'kegiatan' => $kegiatan, 'identity' => (new CardIdentitySettingsService())->read(),
            'cards' => $rows, 'rombelOptions' => $rombel, 'roomOptions' => $rooms,
            'scope' => $scope, 'value' => $value, 'page' => $page, 'pages' => $pages,
            'total' => $total, 'incomplete' => $incomplete,
        ];
    }
}
