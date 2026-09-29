<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class TokenService
{
    private const TOKEN_LENGTH = 6;
    private const TOKEN_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function read(): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $row = $this->row($db, true);
            $row = $this->lazyRotate($db, $row);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Token gagal.');
            return ['ok' => true, 'status' => 200, 'data' => $this->publicRow($row)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Baca Token gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(500, 'TOKEN_READ_FAILED', 'Pengaturan Token tidak dapat dibaca.');
        }
    }

    public function setState(bool $enabled, array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $before = $this->row($db, true);
            $update = ['enabled' => $enabled ? 1 : 0, 'updated_by' => (int) ($actor['user_id'] ?? 0)];

            if ($enabled) {
                $current = trim((string) ($before['current_token'] ?? ''));
                $next = trim((string) ($before['next_token'] ?? ''));
                if ($current === '') $current = $this->generateToken();
                if ($next === '' || $next === $current) $next = $this->generateDistinct($current);
                $update['current_token'] = $current;
                $update['next_token'] = $next;
                $update['rotated_at'] = $before['rotated_at'] ?: date('Y-m-d H:i:s');
                $update['next_rotate_at'] = $this->nextRotateAt($before['auto_rotate_minutes'] ?? null);
            } else {
                $update['next_rotate_at'] = null;
            }

            $db->table('token_control')->where('id', 1)->update($update);
            $after = $this->row($db, false);
            $this->audit('TOKEN_STATE', $before, $after, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Token state gagal.');
            return ['ok' => true, 'status' => 200, 'data' => $this->publicRow($after)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah Token state gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'TOKEN_STATE_FAILED', 'Status Token tidak dapat diubah.');
        }
    }

    public function generate(array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $before = $this->row($db, true);
            $current = $this->generateToken();
            $next = $this->generateDistinct($current);
            $db->table('token_control')->where('id', 1)->update([
                'current_token' => $current,
                'next_token' => $next,
                'rotated_at' => date('Y-m-d H:i:s'),
                'next_rotate_at' => (int) $before['enabled'] === 1
                    ? $this->nextRotateAt($before['auto_rotate_minutes'] ?? null)
                    : null,
                'updated_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            $after = $this->row($db, false);
            $this->audit('TOKEN_GENERATE', $before, $after, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit generate Token gagal.');
            return ['ok' => true, 'status' => 200, 'data' => $this->publicRow($after)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Generate Token gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'TOKEN_GENERATE_FAILED', 'Token baru tidak dapat dibuat.');
        }
    }

    public function rotate(array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $before = $this->row($db, true);
            $current = trim((string) ($before['next_token'] ?? ''));
            if ($current === '') $current = $this->generateToken();
            $next = $this->generateDistinct($current);

            $db->table('token_control')->where('id', 1)->update([
                'current_token' => $current,
                'next_token' => $next,
                'rotated_at' => date('Y-m-d H:i:s'),
                'next_rotate_at' => (int) $before['enabled'] === 1
                    ? $this->nextRotateAt($before['auto_rotate_minutes'] ?? null)
                    : null,
                'updated_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            $after = $this->row($db, false);
            $this->audit('TOKEN_ROTATE', $before, $after, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit rotate Token gagal.');
            return ['ok' => true, 'status' => 200, 'data' => $this->publicRow($after)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Rotate Token gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'TOKEN_ROTATE_FAILED', 'Token tidak dapat diputar.');
        }
    }

    public function setAutoRotate(mixed $minutes, array $actor): array
    {
        $value = null;
        if ($minutes !== null && $minutes !== '' && $minutes !== false) {
            $parsed = is_scalar($minutes)
                ? filter_var($minutes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]])
                : false;
            if (!is_int($parsed))
                return $this->error(422, 'VALIDATION_FAILED', 'Interval rotasi harus 1–1440 menit atau dikosongkan.');
            $value = $parsed;
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $before = $this->row($db, true);
            $db->table('token_control')->where('id', 1)->update([
                'auto_rotate_minutes' => $value,
                'next_rotate_at' => ((int) $before['enabled'] === 1 && $value !== null)
                    ? date('Y-m-d H:i:s', time() + ($value * 60))
                    : null,
                'updated_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            $after = $this->row($db, false);
            $this->audit('TOKEN_AUTO_ROTATE', $before, $after, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit auto-rotate gagal.');
            return ['ok' => true, 'status' => 200, 'data' => $this->publicRow($after)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Auto rotate Token gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'TOKEN_AUTO_ROTATE_FAILED', 'Pengaturan auto-rotate tidak dapat disimpan.');
        }
    }

    public function validateParticipantToken(string $provided): ?array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $row = $this->row($db, true);
            $row = $this->lazyRotate($db, $row);
            $enabled = (int) $row['enabled'] === 1;
            $expected = mb_strtoupper(trim((string) ($row['current_token'] ?? '')), 'UTF-8');
            $actual = mb_strtoupper(trim($provided), 'UTF-8');

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit validasi Token gagal.');

            if (!$enabled) return null;
            if ($expected === '' || $actual === '' || !hash_equals($expected, $actual)) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'code' => 'TOKEN_INVALID',
                    'message' => 'Token ujian tidak sesuai.',
                ];
            }
            return null;
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Validasi Token gagal: {message}', ['message' => $e->getMessage()]);
            return [
                'ok' => false,
                'status' => 503,
                'code' => 'TOKEN_UNAVAILABLE',
                'message' => 'Token ujian belum dapat diverifikasi. Coba kembali.',
            ];
        }
    }

    private function lazyRotate($db, array $row): array
    {
        if ((int) $row['enabled'] !== 1) return $row;
        $minutes = (int) ($row['auto_rotate_minutes'] ?? 0);
        if ($minutes < 1) return $row;

        $nextAt = strtotime((string) ($row['next_rotate_at'] ?? ''));
        if ($nextAt !== false && $nextAt > time()) return $row;

        $current = trim((string) ($row['next_token'] ?? ''));
        if ($current === '') $current = $this->generateToken();
        $next = $this->generateDistinct($current);
        $now = date('Y-m-d H:i:s');
        $db->table('token_control')->where('id', 1)->update([
            'current_token' => $current,
            'next_token' => $next,
            'rotated_at' => $now,
            'next_rotate_at' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
        ]);
        return $this->row($db, false);
    }

    private function row($db, bool $lock): array
    {
        if ($lock) {
            $row = $db->query('SELECT * FROM token_control WHERE id = 1 FOR UPDATE')->getRowArray();
        } else {
            $row = $db->table('token_control')->where('id', 1)->get()->getRowArray();
        }

        if ($row !== null) return $row;
        $db->table('token_control')->insert(['id' => 1, 'enabled' => 0]);
        return $db->table('token_control')->where('id', 1)->get()->getRowArray() ?? [
            'id' => 1,
            'enabled' => 0,
            'current_token' => null,
            'next_token' => null,
            'auto_rotate_minutes' => null,
            'rotated_at' => null,
            'next_rotate_at' => null,
        ];
    }

    private function publicRow(array $row): array
    {
        return [
            'enabled' => (bool) $row['enabled'],
            'current_token' => $row['current_token'],
            'next_token' => $row['next_token'],
            'auto_rotate_minutes' => $row['auto_rotate_minutes'] === null ? null : (int) $row['auto_rotate_minutes'],
            'rotated_at' => $row['rotated_at'],
            'next_rotate_at' => $row['next_rotate_at'],
        ];
    }

    private function nextRotateAt(mixed $minutes): ?string
    {
        $value = (int) ($minutes ?? 0);
        return $value > 0 ? date('Y-m-d H:i:s', time() + ($value * 60)) : null;
    }

    private function generateDistinct(string $other): string
    {
        do {
            $token = $this->generateToken();
        } while ($token === $other);
        return $token;
    }

    private function generateToken(): string
    {
        $result = '';
        $max = strlen(self::TOKEN_CHARS) - 1;
        for ($i = 0; $i < self::TOKEN_LENGTH; $i++)
            $result .= self::TOKEN_CHARS[random_int(0, $max)];
        return $result;
    }

    private function audit(string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log(
            'MANAGER',
            (int) ($actor['user_id'] ?? 0),
            $action,
            'PELAKSANAAN',
            $action,
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'token_control',
            1,
            $before,
            $after
        );
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
