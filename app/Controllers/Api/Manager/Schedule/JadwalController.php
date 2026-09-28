<?php

namespace App\Controllers\Api\Manager\Schedule;

use App\Controllers\BaseController;
use App\Services\JadwalService;
use App\Traits\ApiResponseTrait;

class JadwalController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->apiSuccess((new JadwalService())->list($this->request->getGet()));
    }

    public function options()
    {
        return $this->apiSuccess((new JadwalService())->options());
    }

    public function show(string $id)
    {
        $item = (new JadwalService())->find((int) $id);
        return $item === null
            ? $this->apiError('NOT_FOUND', 'Jadwal utama tidak ditemukan.', 404)
            : $this->apiSuccess(['item' => $item]);
    }

    public function create()
    {
        return $this->respond((new JadwalService())->save($this->payload(), null, $this->actor()));
    }

    public function update(string $id)
    {
        return $this->respond((new JadwalService())->save($this->payload(), (int) $id, $this->actor()));
    }

    public function access(string $id)
    {
        $payload = $this->payload();
        return $this->respond((new JadwalService())->changeAccess((int) $id,
            is_scalar($payload['access_state'] ?? null) ? (string) $payload['access_state'] : '', $this->actor()));
    }

    public function remove(string $id)
    {
        return $this->respond((new JadwalService())->delete((int) $id, $this->actor()));
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
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['item' => $result['item']], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
