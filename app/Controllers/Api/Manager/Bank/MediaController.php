<?php

namespace App\Controllers\Api\Manager\Bank;

use App\Controllers\BaseController;
use App\Services\QuestionMediaService;
use App\Traits\ApiResponseTrait;
use Config\Database;

class MediaController extends BaseController
{
    use ApiResponseTrait;

    public function upload()
    {
        return $this->respond((new QuestionMediaService())->upload($this->request->getFile('file'), $this->actor()));
    }

    public function video()
    {
        $json = $this->request->getJSON(true);
        $url = is_array($json) && is_string($json['url'] ?? null) ? $json['url'] : '';
        return $this->respond((new QuestionMediaService())->addVideo($url, $this->actor()));
    }

    public function show(string $id)
    {
        $row = Database::connect()->table('media_assets')->where('id', (int) $id)->where('status', 'ACTIVE')->get()->getRowArray();
        if ($row === null) return $this->apiError('NOT_FOUND', 'Media tidak ditemukan.', 404);
        if ($row['storage_type'] === 'EXTERNAL') return $this->apiSuccess(['kind' => $row['media_kind'], 'url' => $row['external_url']]);
        if (!preg_match('~^question-media/[a-f0-9]{40}\.(?:jpg|png|webp|mp3|ogg|m4a)$~D', (string) $row['file_path']))
            return $this->apiError('NOT_FOUND', 'File media tidak tersedia.', 404);
        $path = WRITEPATH . 'uploads/' . $row['file_path'];
        if (!is_file($path)) return $this->apiError('NOT_FOUND', 'File media tidak tersedia.', 404);
        return $this->response->setHeader('Content-Type', $row['mime_type'])
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody(file_get_contents($path));
    }

    private function actor(): int
    {
        return (int) (session()->get('manager_auth')['user_id'] ?? 0);
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false) ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status']);
    }
}
