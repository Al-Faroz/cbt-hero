<?php

namespace App\Controllers\Api\Participant;

use App\Controllers\BaseController;
use App\Services\ClientEventService;
use App\Traits\ApiResponseTrait;

class ClientEventController extends BaseController
{
    use ApiResponseTrait;

    public function record(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        $json = $this->request->getJSON(true);

        $result = (new ClientEventService())->record(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            is_array($json) ? $json : [],
            trim((string) $this->request->getHeaderLine('X-CBT-Client-Id')),
            (int) $this->request->getHeaderLine('X-CBT-Client-Generation')
        );

        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status']);
    }
}
