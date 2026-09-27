<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class CardIdentitySettingsService
{
    private const KEYS = ['card_institution_name', 'card_institution_address', 'card_cbt_url', 'card_logo_file'];
    private const LOGO_DIR = 'uploads/card-logos/';

    public function read(): array
    {
        $rows = Database::connect()->table('sys_settings')->select('setting_key, setting_value')
            ->whereIn('setting_key', self::KEYS)->where('is_secret', 0)->get()->getResultArray();
        $values = array_fill_keys(self::KEYS, '');
        foreach ($rows as $row) $values[$row['setting_key']] = $row['setting_value'];
        $hasCustomLogo = $this->validFilename($values['card_logo_file'])
            && is_file(WRITEPATH . self::LOGO_DIR . $values['card_logo_file']);
        return [
            'institution_name' => $values['card_institution_name'],
            'institution_address' => $values['card_institution_address'],
            'cbt_url' => $values['card_cbt_url'],
            'logo_url' => $hasCustomLogo
                ? base_url('manager/system/card-logo')
                : base_url(config('Branding')->logo),
            'custom_logo' => $hasCustomLogo,
        ];
    }

    public function logoPath(): ?string
    {
        $row = Database::connect()->table('sys_settings')->select('setting_value')
            ->where('setting_key', 'card_logo_file')->where('is_secret', 0)->get()->getRowArray();
        $filename = (string) ($row['setting_value'] ?? '');
        if (! $this->validFilename($filename)) return null;
        $path = WRITEPATH . self::LOGO_DIR . $filename;
        return is_file($path) ? $path : null;
    }

    public function save(array $payload, $file, array $actor): array
    {
        $name = trim(is_string($payload['institution_name'] ?? null) ? $payload['institution_name'] : '');
        $address = trim(is_string($payload['institution_address'] ?? null) ? $payload['institution_address'] : '');
        $url = trim(is_string($payload['cbt_url'] ?? null) ? $payload['cbt_url'] : '');
        $resetLogo = ($payload['use_default_logo'] ?? '') === '1';
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150 || strpbrk($name, '<>') !== false)
            $errors['institution_name'] = 'Nama instansi wajib 1–150 karakter tanpa tag HTML.';
        if ($address === '' || mb_strlen($address) > 300 || strpbrk($address, '<>') !== false)
            $errors['institution_address'] = 'Alamat wajib 1–300 karakter tanpa tag HTML.';
        $parts = parse_url($url);
        if (strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false || ! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']))
            $errors['cbt_url'] = 'Gunakan URL lengkap http(s) tanpa kredensial atau fragmen.';
        $hasFile = $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
        if ($hasFile) {
            if ($resetLogo || ! $file->isValid() || $file->getSize() > 1024 * 1024
                || $file->getSize() < 1 || ! in_array(strtolower($file->getClientExtension()), ['png', 'jpg', 'jpeg'], true)) {
                $errors['logo'] = 'Pilih PNG/JPG maksimal 1 MB, atau gunakan logo bawaan.';
            } else {
                $info = @getimagesize($file->getTempName());
                if ($info === false || ! in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)
                    || ($info[0] ?? 0) > 2000 || ($info[1] ?? 0) > 2000 || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1)
                    $errors['logo'] = 'Logo harus PNG/JPG valid berukuran maksimal 2000×2000 piksel.';
            }
        }
        if ($errors !== []) return $this->error(422, 'VALIDATION_FAILED', 'Identitas kartu belum valid.', $errors);
        $stored = null;
        try {
            if ($hasFile) {
                $dir = WRITEPATH . self::LOGO_DIR;
                if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir))
                    throw new RuntimeException('Folder logo tidak tersedia.');
                $stored = bin2hex(random_bytes(16)) . (($info['mime'] ?? '') === 'image/png' ? '.png' : '.jpg');
                $file->move($dir, $stored);
            }
            $db = Database::connect(); $db->transBegin();
            try {
                $values = ['card_institution_name' => $name, 'card_institution_address' => $address,
                    'card_cbt_url' => $url];
                foreach (self::KEYS as $key) {
                    $row = $db->query('SELECT setting_value, is_secret FROM sys_settings WHERE setting_key = ? FOR UPDATE',
                        [$key])->getRowArray();
                    if ($row !== null && (int) $row['is_secret'] !== 0)
                        throw new RuntimeException('Key pengaturan ditandai rahasia.');
                    if ($key === 'card_logo_file') {
                        $values[$key] = $resetLogo ? '' : ($stored ?? $row['setting_value'] ?? '');
                    }
                    $data = ['setting_value' => $values[$key], 'updated_by' => (int) $actor['user_id']];
                    if ($row === null) $db->table('sys_settings')->insert(['setting_key' => $key,
                        'value_type' => 'STRING', 'is_secret' => 0] + $data);
                    else $db->table('sys_settings')->where('setting_key', $key)->update($data);
                }
                (new AuditService())->log('MANAGER', (int) $actor['user_id'], 'UPDATE_CARD_IDENTITY',
                    'SETTINGS', 'Ubah identitas kartu ujian.', (string) ($actor['ip'] ?? ''),
                    (string) ($actor['agent'] ?? ''), 'sys_settings');
                if ($db->transStatus() === false || $db->transCommit() === false)
                    throw new RuntimeException('Commit identitas kartu gagal.');
                return ['ok' => true, 'status' => 200, 'settings' => [
                    'institution_name' => $name, 'institution_address' => $address, 'cbt_url' => $url,
                    'custom_logo' => $values['card_logo_file'] !== '',
                    'logo_url' => $values['card_logo_file'] !== ''
                        ? base_url('manager/system/card-logo') : base_url(config('Branding')->logo),
                ]];
            } catch (Throwable $e) {
                $db->transRollback(); throw $e;
            }
        } catch (Throwable $e) {
            if ($stored !== null) @unlink(WRITEPATH . self::LOGO_DIR . $stored);
            log_message('error', 'Identitas kartu gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Identitas kartu tidak dapat disimpan.');
        }
    }

    private function validFilename(string $name): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}\.(?:png|jpg)$/D', $name);
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
