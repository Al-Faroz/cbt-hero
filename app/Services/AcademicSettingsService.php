<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class AcademicSettingsService
{
    private const KEYS = ['default_tahun_pelajaran', 'default_semester'];

    public function read(): array
    {
        $rows = Database::connect()->table('sys_settings')
            ->select('setting_key, setting_value')
            ->whereIn('setting_key', self::KEYS)->where('is_secret', 0)
            ->get()->getResultArray();
        $values = array_fill_keys(self::KEYS, '');
        foreach ($rows as $row) {
            $values[$row['setting_key']] = $row['setting_value'];
        }
        return $values;
    }

    public function save(array $payload, array $actor): array
    {
        $year = $payload['default_tahun_pelajaran'] ?? null;
        $semester = $payload['default_semester'] ?? null;
        $errors = [];
        if (! is_string($year) || ! preg_match('/^(20[0-9]{2})\/(20[0-9]{2})$/D', trim($year), $parts)
            || (int) ($parts[2] ?? 0) !== (int) ($parts[1] ?? 0) + 1) {
            $errors['default_tahun_pelajaran'] = 'Gunakan format 2026/2027 (tahun kedua harus berurutan).';
        }
        if (! is_string($semester) || ! in_array(strtoupper(trim($semester)), ['GANJIL', 'GENAP'], true)) {
            $errors['default_semester'] = 'Pilih GANJIL atau GENAP.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'status' => 422, 'fields' => $errors,
                'message' => 'Pengaturan akademik belum valid.'];
        }
        $values = ['default_tahun_pelajaran' => trim($year),
            'default_semester' => strtoupper(trim($semester))];
        $db = Database::connect();
        $db->transBegin();
        try {
            // Lock kedua key secara berurutan agar perubahan bersama tidak saling mendahului.
            $old = [];
            foreach (self::KEYS as $key) {
                $row = $db->query('SELECT setting_value, is_secret FROM sys_settings WHERE setting_key = ? FOR UPDATE',
                    [$key])->getRowArray();
                if ($row !== null && (int) $row['is_secret'] !== 0) {
                    throw new RuntimeException('Key pengaturan telah ditandai rahasia.');
                }
                $old[$key] = $row['setting_value'] ?? '';
                if ($row === null) {
                    $db->table('sys_settings')->insert(['setting_key' => $key,
                        'setting_value' => $values[$key], 'value_type' => 'STRING',
                        'is_secret' => 0, 'updated_by' => (int) $actor['user_id']]);
                } else {
                    $db->table('sys_settings')->where('setting_key', $key)->update([
                        'setting_value' => $values[$key], 'updated_by' => (int) $actor['user_id']]);
                }
            }
            if ($old !== $values) {
                (new AuditService())->log('MANAGER', (int) $actor['user_id'], 'UPDATE_ACADEMIC_SETTINGS',
                    'SETTINGS', 'Ubah default Tahun Pelajaran dan Semester.',
                    (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
                    'sys_settings', null, $old, $values);
            }
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit pengaturan gagal.');
            }
            return ['ok' => true, 'status' => 200, 'settings' => $values];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Pengaturan akademik gagal: {message}', ['message' => $e->getMessage()]);
            return ['ok' => false, 'status' => 409, 'message' => 'Pengaturan tidak dapat disimpan.'];
        }
    }
}
