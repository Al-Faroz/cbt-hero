<?php

namespace App\Controllers\Api\Manager\Schedule;

use App\Controllers\BaseController;
use App\Services\SusulanService;
use App\Traits\ApiResponseTrait;

class SusulanController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $mainId)
    {
        return $this->respondData((new SusulanService())->list((int) $mainId));
    }

    public function candidates(string $mainId)
    {
        return $this->respondData((new SusulanService())->candidates((int) $mainId, $this->request->getGet()));
    }

    public function create(string $mainId)
    {
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));
        return $this->respond((new SusulanService())->create((int) $mainId, $this->payload(), $key, $this->actor()));
    }

    public function cancelTarget(string $susulanId, string $targetId)
    {
        return $this->respond((new SusulanService())->cancelTarget(
            (int) $susulanId, (int) $targetId, $this->actor()
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

    private function respondData(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['item' => $result['item'], 'replayed' => (bool) ($result['replayed'] ?? false)], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
