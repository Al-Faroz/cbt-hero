<?php

namespace App\Services;

use Config\Database;

class KegiatanPreflightService
{
    private const ISSUES = [
        'inactive' => [
            'label' => 'Keanggotaan atau akun Peserta tidak aktif',
            'condition' => "pk.status <> 'ACTIVE' OR p.status <> 'ACTIVE'",
        ],
        'number' => [
            'label' => 'Nomor Peserta belum tersedia',
            'condition' => "pk.nomor_peserta IS NULL OR pk.nomor_peserta = ''",
        ],
        'room' => [
            'label' => 'Ruang belum dipasang atau tidak aktif',
            'condition' => "pk.ruang_id IS NULL OR r.id IS NULL OR r.status <> 'ACTIVE'",
        ],
        'credential' => [
            'label' => 'Username atau password cetak belum siap',
            'condition' => "p.username IS NULL OR p.username = '' OR p.password_hash IS NULL "
                . "OR p.password_hash = '' OR p.password_encrypted IS NULL OR p.password_encrypted = '' "
                . "OR p.credential_status <> 'READY'",
        ],
    ];

    public function inspect(int $kegiatanId): ?array
    {
        $db = Database::connect();
        $kegiatan = $kegiatanId > 0 ? $db->table('kegiatan')->select('id, nama, tahun_pelajaran, semester')
            ->where('id', $kegiatanId)->get()->getRowArray() : null;
        if ($kegiatan === null) return null;

        $joins = ' FROM peserta_kegiatan pk JOIN peserta p ON p.id = pk.peserta_id '
            . 'LEFT JOIN ruang r ON r.id = pk.ruang_id WHERE pk.kegiatan_id = ?';
        $select = 'SELECT COUNT(*) AS total';
        foreach (self::ISSUES as $key => $issue) {
            $select .= ', COALESCE(SUM(CASE WHEN (' . $issue['condition'] . ') THEN 1 ELSE 0 END), 0) AS ' . $key;
        }
        $anyIssue = implode(' OR ', array_map(static fn ($issue) => '(' . $issue['condition'] . ')', self::ISSUES));
        $select .= ', COALESCE(SUM(CASE WHEN (' . $anyIssue . ') THEN 1 ELSE 0 END), 0) AS affected';
        $counts = $db->query($select . $joins, [$kegiatanId])->getRowArray();
        $identity = (new CardIdentitySettingsService())->read();
        $identityComplete = trim($identity['institution_name']) !== ''
            && trim($identity['institution_address']) !== '' && trim($identity['cbt_url']) !== '';

        $issues = [];
        foreach (self::ISSUES as $key => $issue) {
            $count = (int) $counts[$key];
            $samples = [];
            if ($count > 0) {
                $samples = $db->query('SELECT pk.id, pk.nama_snapshot AS nama, pk.rombel_snapshot AS rombel, '
                    . 'pk.nomor_peserta AS nomor' . $joins . ' AND (' . $issue['condition'] . ') '
                    . 'ORDER BY pk.rombel_snapshot, pk.nama_snapshot, pk.id LIMIT 20', [$kegiatanId])
                    ->getResultArray();
            }
            $issues[$key] = ['label' => $issue['label'], 'count' => $count, 'samples' => $samples];
        }
        $total = (int) $counts['total'];
        $affected = (int) $counts['affected'];
        $findings = array_sum(array_column($issues, 'count'));
        return [
            'kegiatan' => $kegiatan, 'total' => $total, 'issues' => $issues,
            'affected' => $affected, 'findings' => $findings,
            'identityComplete' => $identityComplete,
            'administrativeReady' => $total > 0 && $identityComplete && $affected === 0,
        ];
    }
}
