<?php

namespace App\Controllers\Api\Participant;

use App\Controllers\BaseController;
use App\Services\AttemptBootstrapService;
use App\Traits\ApiResponseTrait;

class BootstrapController extends BaseController
{
    use ApiResponseTrait;

    public function show(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        return $this->respond((new AttemptBootstrapService())->bootstrap(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            trim((string) $this->request->getHeaderLine('X-CBT-Client-Id')),
            (int) $this->request->getHeaderLine('X-CBT-Client-Generation')
        ));
    }

    public function media(string $attemptId, string $mediaId)
    {
        $auth = session()->get('participant_auth');
        $result = (new AttemptBootstrapService())->media(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            (int) $mediaId
        );
        if (!($result['ok'] ?? false))
            return $this->apiError($result['code'], $result['message'], $result['status']);

        $row = $result['data'];
        if (($row['storage_type'] ?? '') !== 'LOCAL'
            || !preg_match('~^question-media/[a-f0-9]{40}\.(?:jpg|png|webp)$~D', (string) ($row['file_path'] ?? ''))) {
            return $this->apiError('NOT_FOUND', 'File media tidak tersedia.', 404);
        }
        $path = WRITEPATH . 'uploads/' . $row['file_path'];
        if (!is_file($path)) return $this->apiError('NOT_FOUND', 'File media tidak tersedia.', 404);

        return $this->response
            ->setHeader('Content-Type', (string) $row['mime_type'])
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody(file_get_contents($path));
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
