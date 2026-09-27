<?php

namespace App\Controllers\Api\Manager\Bank;

use App\Controllers\BaseController;
use App\Services\BankTypeConfigService;
use App\Traits\ApiResponseTrait;

class BankTypeConfigController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $id)
    {
        return $this->respond((new BankTypeConfigService())->read((int) $id));
    }

    public function update(string $id)
    {
        $payload = $this->request->getJSON(true);
        return $this->respond((new BankTypeConfigService())->save((int) $id,
            is_array($payload) ? $payload : [], [
                'user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
                'ip' => $this->request->getIPAddress(), 'agent' => (string) $this->request->getUserAgent(),
            ]));
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
