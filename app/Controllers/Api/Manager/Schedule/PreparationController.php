<?php

namespace App\Controllers\Api\Manager\Schedule;

use App\Controllers\BaseController;
use App\Services\PreparationService;
use App\Traits\ApiResponseTrait;

class PreparationController extends BaseController
{
    use ApiResponseTrait;

    public function status(string $jadwalId)
    {
        return $this->respondData((new PreparationService())->status((int) $jadwalId));
    }

    public function prepare(string $jadwalId)
    {
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));
        return $this->respondData((new PreparationService())->prepare(
            (int) $jadwalId,
            $this->payload(),
            $key,
            $this->actor(),
            false
        ));
    }

    public function rebuild(string $jadwalId)
    {
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));
        return $this->respondData((new PreparationService())->prepare(
            (int) $jadwalId,
            $this->payload(),
            $key,
            $this->actor(),
            true
        ));
    }

    public function assignments(string $jadwalId)
    {
        return $this->respondData((new PreparationService())->assignments((int) $jadwalId));
    }

    public function detail(string $assignmentId)
    {
        return $this->respondData((new PreparationService())->detail((int) $assignmentId));
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

    private function respondData(array $result)
    {
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
