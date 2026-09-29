<?php

namespace App\Controllers\Api\Manager\Scoring;

use App\Controllers\BaseController;
use App\Services\ManualOverrideService;
use App\Services\RescoreService;
use App\Traits\ApiResponseTrait;

class CorrectionController extends BaseController
{
    use ApiResponseTrait;

    public function manualScore(string $responseId)
    {
        $payload = $this->payload();
        return $this->respond((new ManualOverrideService())->apply(
            (int) $responseId,
            $payload['score'] ?? null,
            $payload['reason'] ?? null,
            $this->actor()
        ));
    }

    public function rescore(string $jadwalId)
    {
        return $this->respond((new RescoreService())->execute(
            (int) $jadwalId,
            trim((string) $this->request->getHeaderLine('Idempotency-Key')),
            $this->actor()
        ));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : [];
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
