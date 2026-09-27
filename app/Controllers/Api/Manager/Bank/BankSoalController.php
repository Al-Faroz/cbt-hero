<?php

namespace App\Controllers\Api\Manager\Bank;

use App\Controllers\BaseController;
use App\Services\BankSoalService;
use App\Traits\ApiResponseTrait;

class BankSoalController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->apiSuccess((new BankSoalService())->list($this->request->getGet()));
    }

    public function options()
    {
        return $this->apiSuccess((new BankSoalService())->options());
    }

    public function show(string $id)
    {
        $item = (new BankSoalService())->find((int) $id);
        return $item === null ? $this->apiError('NOT_FOUND', 'Bank Soal tidak ditemukan.', 404)
            : $this->apiSuccess(['item' => $item]);
    }

    public function create()
    {
        return $this->respond((new BankSoalService())->save($this->payload(), null, $this->actor()));
    }

    public function update(string $id)
    {
        return $this->respond((new BankSoalService())->save($this->payload(), (int) $id, $this->actor()));
    }

    public function remove(string $id)
    {
        return $this->respond((new BankSoalService())->delete((int) $id, $this->actor()));
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
            'ip' => $this->request->getIPAddress(), 'agent' => (string) $this->request->getUserAgent(),
        ];
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['item' => $result['item']], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
