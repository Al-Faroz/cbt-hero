<?php

namespace App\Controllers\Api\Manager\Scoring;

use App\Controllers\BaseController;
use App\Services\ResultFinalizationService;
use App\Traits\ApiResponseTrait;

class FinalizationController extends BaseController
{
    use ApiResponseTrait;

    public function finalize(string $jadwalId)
    {
        return $this->respond((new ResultFinalizationService())->finalize(
            (int) $jadwalId,
            trim((string) $this->request->getHeaderLine('Idempotency-Key')),
            $this->actor()
        ));
    }

    private function actor(): array
    {
        return [
            'user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
            'ip' => $this->request->getIPAddress(),
            'agent' => (string) $this->request->getUserAgent(),
        ];
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError(
                $result['code'],
                $result['message'],
                $result['status'],
                $result['fields'] ?? []
            );
    }
}
