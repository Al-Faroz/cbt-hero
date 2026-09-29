<?php

namespace App\Controllers\Api\Participant;

use App\Controllers\BaseController;
use App\Services\AttemptFinalizeService;
use App\Traits\ApiResponseTrait;

class FinalizeController extends BaseController
{
    use ApiResponseTrait;

    public function finalize(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        $json = $this->request->getJSON(true);
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));

        $result = (new AttemptFinalizeService())->finalize(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            is_array($json) ? $json : [],
            $key,
            trim((string) $this->request->getHeaderLine('X-CBT-Client-Id')),
            (int) $this->request->getHeaderLine('X-CBT-Client-Generation')
        );

        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status']);
    }
}
