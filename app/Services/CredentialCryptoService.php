<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class CredentialCryptoService
{
    private const KEY_NAME = 'credential_encryption_key';
    private const CIPHER = 'aes-256-gcm';
    private ?string $cachedKey = null;

    public function ensureKey(): void
    {
        $this->key(true);
    }

    public function encryptPrintablePassword(string $password): string
    {
        $key = $this->key(true);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($password, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Enkripsi credential gagal.');
        }
        return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decryptPrintablePassword(string $value): string
    {
        if (! str_starts_with($value, 'v1:')) {
            throw new RuntimeException('Versi credential terenkripsi tidak dikenal.');
        }
        $raw = base64_decode(substr($value, 3), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Credential terenkripsi tidak valid.');
        }
        $password = openssl_decrypt(
            substr($raw, 28), self::CIPHER, $this->key(false), OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16)
        );
        if ($password === false) {
            throw new RuntimeException('Credential tidak dapat didekripsi.');
        }
        return $password;
    }

    private function key(bool $create): string
    {
        if ($this->cachedKey !== null) {
            return $this->cachedKey;
        }
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Ekstensi OpenSSL diperlukan untuk credential.');
        }
        $db = Database::connect();
        $row = $db->table('sys_settings')->select('setting_value')
            ->where('setting_key', self::KEY_NAME)->get()->getRowArray();
        if ($row === null && $create) {
            try {
                $db->table('sys_settings')->insert([
                    'setting_key' => self::KEY_NAME,
                    'setting_value' => bin2hex(random_bytes(32)),
                    'value_type' => 'SECRET',
                    'is_secret' => 1,
                ]);
            } catch (Throwable $e) {
                // Bootstrap serentak: gunakan key yang sudah dimenangkan request lain.
                log_message('notice', 'Credential key sudah dibuat request lain.');
            }
            $row = $db->table('sys_settings')->select('setting_value')
                ->where('setting_key', self::KEY_NAME)->get()->getRowArray();
        }
        $hex = (string) ($row['setting_value'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/D', $hex)) {
            throw new RuntimeException('Credential encryption key tidak tersedia atau tidak valid.');
        }
        $this->cachedKey = hex2bin($hex);
        return $this->cachedKey;
    }
}
