<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;
use Throwable;

class QuestionMediaService
{
    private const DRIVE_PROVIDER = 'GDRIVE';

    public function importImage(string $bytes, int $actor): int
    {
        if (strlen($bytes) > 5 * 1024 * 1024 || strlen($bytes) < 1 || @getimagesizefromstring($bytes) === false)
            throw new RuntimeException('Gambar dokumen tidak valid atau lebih dari 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if ($extension === null) throw new RuntimeException('Gambar harus JPG, PNG, atau WebP.');
        $folder = WRITEPATH . 'uploads/question-media/';
        if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder))
            throw new RuntimeException('Penyimpanan media tidak tersedia.');
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        if (file_put_contents($folder . $name, $bytes, LOCK_EX) === false) throw new RuntimeException('Gambar tidak dapat disimpan.');
        try {
            $db = Database::connect();
            $db->table('media_assets')->insert(['storage_type' => 'LOCAL', 'media_kind' => 'IMAGE',
                'file_path' => 'question-media/' . $name, 'mime_type' => $mime,
                'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                'status' => 'ACTIVE', 'created_by' => $actor]);
            return (int) $db->insertID();
        } catch (Throwable $e) { @unlink($folder . $name); throw $e; }
    }

    public function discardImport(int $id): void
    {
        $db = Database::connect();
        $asset = $db->table('media_assets')->where('id', $id)->get()->getRowArray();
        if ($asset === null || $asset['storage_type'] !== 'LOCAL') return;
        $db->table('media_assets')->where('id', $id)->delete();
        @unlink(WRITEPATH . 'uploads/' . $asset['file_path']);
    }

    public function upload(?UploadedFile $file, int $actor): array
    {
        if ($file === null || !$file->isValid() || $file->getSize() > 10 * 1024 * 1024
            || $file->getSize() < 1) return $this->error(422, 'VALIDATION_FAILED', 'Gambar maksimal 10 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime]))
            return $this->error(422, 'VALIDATION_FAILED', 'Hanya gambar JPG, PNG, atau WebP yang dapat diunggah. Audio/video menggunakan link Google Drive.');
        $folder = WRITEPATH . 'uploads/question-media/';
        if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder))
            return $this->error(503, 'STORAGE_ERROR', 'Penyimpanan media tidak tersedia.');
        $name = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
        try {
            $sha = hash_file('sha256', $file->getTempName());
            $file->move($folder, $name);
            $db = Database::connect();
            $db->table('media_assets')->insert(['storage_type' => 'LOCAL', 'media_kind' => 'IMAGE',
                'file_path' => 'question-media/' . $name, 'mime_type' => $mime,
                'size_bytes' => filesize($folder . $name), 'sha256' => $sha,
                'status' => 'ACTIVE', 'created_by' => $actor]);
            return ['ok' => true, 'status' => 201, 'data' => ['id' => (int) $db->insertID(), 'kind' => 'IMAGE']];
        } catch (Throwable $e) {
            @unlink($folder . $name);
            return $this->error(503, 'STORAGE_ERROR', 'Gambar gagal disimpan.');
        }
    }

    public function directiveError(array $data): ?string
    {
        foreach ($this->contentSources($data) as $source) {
            foreach ($this->directiveMatches($source['text']) as $directive) {
                if ($this->driveFileId($directive['url']) === null)
                    return 'Audio/Video hanya mendukung link file Google Drive HTTPS. Gunakan format "Audio: <link Google Drive>" atau "Video: <link Google Drive>".';
            }
        }
        return null;
    }

    public function references(array $data): array
    {
        $references = [];
        foreach ($this->contentSources($data) as $source) {
            if (preg_match_all('/\[\[media:([1-9][0-9]{0,18})\]\]/', $source['text'], $matches)) {
                foreach ($matches[1] as $id) $references[] = ['media_asset_id' => (int) $id,
                    'media_role' => $source['role'], 'option_key' => $source['option_key'],
                    'sort_order' => count($references) + 1];
            }
        }
        return $references;
    }

    public function attach($db, int $revisionId, array $payload, array $actor = []): void
    {
        $refs = $this->references($payload);
        foreach ($refs as $row) {
            $asset = $db->table('media_assets')->select('id, storage_type, media_kind, provider')
                ->where('id', $row['media_asset_id'])->where('status', 'ACTIVE')->get()->getRowArray();
            if ($asset === null) throw new RuntimeException('Referensi media #' . $row['media_asset_id'] . ' tidak tersedia.');
            if (in_array($asset['media_kind'], ['AUDIO', 'VIDEO'], true)
                && ($asset['storage_type'] !== 'EXTERNAL' || $asset['provider'] !== self::DRIVE_PROVIDER))
                throw new RuntimeException('Audio/video hanya boleh menggunakan Google Drive.');
        }

        foreach ($this->contentSources($payload) as $source) {
            foreach ($this->directiveMatches($source['text']) as $directive) {
                $fileId = $this->driveFileId($directive['url']);
                if ($fileId === null) throw new RuntimeException('Link Audio/Video Google Drive tidak valid.');
                $refs[] = [
                    'media_asset_id' => $this->ensureDriveAsset($db, $directive['kind'], $fileId, (int) ($actor['user_id'] ?? 0)),
                    'media_role' => $source['role'], 'option_key' => $source['option_key'],
                    'sort_order' => count($refs) + 1,
                ];
            }
        }

        $seen = [];
        $order = 0;
        foreach ($refs as $row) {
            $signature = $row['media_asset_id'] . '|' . $row['media_role'] . '|' . ($row['option_key'] ?? '');
            if (isset($seen[$signature])) continue;
            $seen[$signature] = true; $row['sort_order'] = ++$order;
            $db->table('soal_revision_media')->insert(['soal_revision_id' => $revisionId] + $row);
        }
    }

    public function drivePreviewUrl(string $fileId): string
    {
        return 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/preview';
    }

    private function ensureDriveAsset($db, string $kind, string $fileId, int $actor): int
    {
        $existing = $db->table('media_assets')->select('id')
            ->where('storage_type', 'EXTERNAL')->where('media_kind', $kind)
            ->where('provider', self::DRIVE_PROVIDER)->where('external_id', $fileId)
            ->where('status', 'ACTIVE')->get()->getRowArray();
        if ($existing !== null) return (int) $existing['id'];

        $db->table('media_assets')->insert([
            'storage_type' => 'EXTERNAL', 'media_kind' => $kind, 'provider' => self::DRIVE_PROVIDER,
            'external_id' => $fileId, 'external_url' => $this->drivePreviewUrl($fileId),
            'status' => 'ACTIVE', 'created_by' => $actor > 0 ? $actor : null,
        ]);
        return (int) $db->insertID();
    }

    private function contentSources(array $data): array
    {
        $sources = [
            ['text' => is_string($data['question_text'] ?? null) ? $data['question_text'] : '', 'role' => 'QUESTION', 'option_key' => null],
            ['text' => is_string($data['stimulus_text'] ?? null) ? $data['stimulus_text'] : '', 'role' => 'STIMULUS', 'option_key' => null],
            ['text' => is_string($data['rubric_text'] ?? null) ? $data['rubric_text'] : '', 'role' => 'RUBRIC', 'option_key' => null],
        ];
        foreach (array_values(is_array($data['options'] ?? null) ? $data['options'] : []) as $i => $option)
            if (is_array($option)) $sources[] = ['text' => is_string($option['text'] ?? null) ? $option['text'] : '',
                'role' => 'OPTION', 'option_key' => chr(65 + $i)];
        foreach (array_values(is_array($data['pairs'] ?? null) ? $data['pairs'] : []) as $i => $pair) {
            if (!is_array($pair)) continue;
            $key = (string) ($i + 1);
            $sources[] = ['text' => is_string($pair['left'] ?? null) ? $pair['left'] : '',
                'role' => 'MATCHING_LEFT', 'option_key' => $key];
            $sources[] = ['text' => is_string($pair['right'] ?? null) ? $pair['right'] : '',
                'role' => 'MATCHING_RIGHT', 'option_key' => $key];
        }
        return $sources;
    }

    private function directiveMatches(string $text): array
    {
        $result = [];
        if (!preg_match_all('~(?:\*{1,2})?\b(Audio|Video)\s*:(?:\*{1,2})?\s*(https?://[^\s<>"\']+)~iu',
            $text, $matches, PREG_SET_ORDER)) return $result;
        foreach ($matches as $match) {
            $url = rtrim((string) $match[2], '.,;!?)]}*');
            $result[] = ['kind' => mb_strtoupper((string) $match[1], 'UTF-8'), 'url' => $url];
        }
        return $result;
    }

    private function driveFileId(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !in_array(strtolower((string) ($parts['host'] ?? '')), ['drive.google.com', 'www.drive.google.com'], true)
            || isset($parts['user']) || isset($parts['pass'])) return null;

        $path = (string) ($parts['path'] ?? '');
        $id = null;
        if (preg_match('~^/file/d/([A-Za-z0-9_-]{10,200})(?:/|$)~D', $path, $match)) $id = $match[1];
        elseif (in_array($path, ['/open', '/uc'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['id'] ?? null;
            if (is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $candidate)) $id = $candidate;
        }
        return $id;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
