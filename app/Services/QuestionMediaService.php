<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;
use Throwable;

class QuestionMediaService
{
    public function importImage(string $bytes, int $actor): int
    {
        if (strlen($bytes) > 5 * 1024 * 1024 || strlen($bytes) < 1 || @getimagesizefromstring($bytes) === false)
            throw new RuntimeException('Gambar Word tidak valid atau lebih dari 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if ($extension === null) throw new RuntimeException('Gambar Word harus JPG, PNG, atau WebP.');
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
            || $file->getSize() < 1) return $this->error(422, 'VALIDATION_FAILED', 'Media maksimal 10 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
        $allowed = ['image/jpeg' => ['IMAGE','jpg'], 'image/png' => ['IMAGE','png'], 'image/webp' => ['IMAGE','webp'],
            'audio/mpeg' => ['AUDIO','mp3'], 'audio/ogg' => ['AUDIO','ogg'], 'audio/mp4' => ['AUDIO','m4a']];
        if (!isset($allowed[$mime])) return $this->error(422, 'VALIDATION_FAILED', 'Hanya gambar JPG/PNG/WebP dan audio MP3/OGG/M4A.');
        [$kind,$ext] = $allowed[$mime];
        $folder = WRITEPATH . 'uploads/question-media/';
        if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder))
            return $this->error(503, 'STORAGE_ERROR', 'Penyimpanan media tidak tersedia.');
        $name = bin2hex(random_bytes(20)) . '.' . $ext;
        try {
            $sha = hash_file('sha256', $file->getTempName());
            $file->move($folder, $name);
            $db = Database::connect();
            $db->table('media_assets')->insert(['storage_type' => 'LOCAL', 'media_kind' => $kind,
                'file_path' => 'question-media/' . $name, 'mime_type' => $mime,
                'size_bytes' => filesize($folder . $name), 'sha256' => $sha,
                'status' => 'ACTIVE', 'created_by' => $actor]);
            return ['ok' => true, 'status' => 201, 'data' => ['id' => (int) $db->insertID(), 'kind' => $kind]];
        } catch (Throwable $e) {
            @unlink($folder . $name);
            return $this->error(503, 'STORAGE_ERROR', 'Media gagal disimpan.');
        }
    }

    public function addVideo(string $url, int $actor): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']))
            return $this->error(422, 'VALIDATION_FAILED', 'Gunakan tautan HTTPS YouTube/Vimeo.');
        $host = strtolower($parts['host'] ?? ''); $provider = null; $id = null;
        if (in_array($host, ['youtube.com','www.youtube.com'], true) && ($parts['path'] ?? '') === '/watch') {
            parse_str($parts['query'] ?? '', $query); $id = $query['v'] ?? null; $provider = 'YOUTUBE';
        } elseif ($host === 'youtu.be') {$id = trim($parts['path'] ?? '', '/'); $provider = 'YOUTUBE';}
        elseif (in_array($host, ['vimeo.com','www.vimeo.com'], true)) {$id = trim($parts['path'] ?? '', '/'); $provider = 'VIMEO';}
        if ($provider === 'YOUTUBE' && (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id))) $id = null;
        if ($provider === 'VIMEO' && (!is_string($id) || !preg_match('/^[0-9]{1,15}$/D', $id))) $id = null;
        if ($id === null || $provider === null) return $this->error(422, 'VALIDATION_FAILED', 'Tautan video tidak didukung.');
        $db = Database::connect();
        $db->table('media_assets')->insert(['storage_type' => 'EXTERNAL', 'media_kind' => 'VIDEO',
            'provider' => $provider, 'external_id' => $id,
            'external_url' => $provider === 'YOUTUBE' ? 'https://www.youtube.com/watch?v=' . $id : 'https://vimeo.com/' . $id,
            'status' => 'ACTIVE', 'created_by' => $actor]);
        return ['ok' => true, 'status' => 201, 'data' => ['id' => (int) $db->insertID(), 'kind' => 'VIDEO']];
    }

    public function references(array $data): array
    {
        $references = [];
        $scan = function (string $text, string $role, ?string $optionKey = null) use (&$references): void {
            if (preg_match_all('/\[\[media:([1-9][0-9]{0,18})\]\]/', $text, $matches))
                foreach ($matches[1] as $id) $references[] = ['media_asset_id' => (int) $id,
                    'media_role' => $role, 'option_key' => $optionKey, 'sort_order' => count($references) + 1];
        };
        $scan(is_string($data['question_text'] ?? null) ? $data['question_text'] : '', 'QUESTION');
        $scan(is_string($data['stimulus_text'] ?? null) ? $data['stimulus_text'] : '', 'STIMULUS');
        $scan(is_string($data['rubric_text'] ?? null) ? $data['rubric_text'] : '', 'RUBRIC');
        foreach (array_values(is_array($data['options'] ?? null) ? $data['options'] : []) as $i => $option)
            if (is_array($option)) $scan(is_string($option['text'] ?? null) ? $option['text'] : '', 'OPTION', chr(65 + $i));
        foreach (array_values(is_array($data['pairs'] ?? null) ? $data['pairs'] : []) as $i => $pair) {
            if (!is_array($pair)) continue;
            $scan(is_string($pair['left'] ?? null) ? $pair['left'] : '', 'MATCHING_LEFT', (string) ($i+1));
            $scan(is_string($pair['right'] ?? null) ? $pair['right'] : '', 'MATCHING_RIGHT', (string) ($i+1));
        }
        return $references;
    }

    public function attach($db, int $revisionId, array $payload): void
    {
        $refs = $this->references($payload);
        foreach ($refs as $row) {
            $asset = $db->table('media_assets')->select('id')->where('id', $row['media_asset_id'])
                ->where('status', 'ACTIVE')->get()->getRowArray();
            if ($asset === null) throw new RuntimeException('Referensi media #' . $row['media_asset_id'] . ' tidak tersedia.');
            $db->table('soal_revision_media')->insert(['soal_revision_id' => $revisionId] + $row);
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
