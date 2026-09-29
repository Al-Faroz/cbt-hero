<?php

namespace App\Controllers\Api\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\KegiatanService;
use App\Traits\ApiResponseTrait;

class KegiatanController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->apiSuccess((new KegiatanService())->list($this->request->getGet()));
    }

    public function defaults()
    {
        return $this->apiSuccess(['defaults' => (new KegiatanService())->defaults()]);
    }

    public function show(string $id)
    {
        $item = (new KegiatanService())->find((int) $id);
        return $item === null
            ? $this->apiError('NOT_FOUND', 'Kegiatan tidak ditemukan.', 404)
            : $this->apiSuccess(['item' => $item]);
    }

    public function create()
    {
        return $this->respond((new KegiatanService())->save($this->payload(), null, $this->actor()));
    }

    public function update(string $id)
    {
        return $this->respond((new KegiatanService())->save($this->payload(), (int) $id, $this->actor()));
    }

    public function remove(string $id)
    {
        return $this->respond((new KegiatanService())->delete((int) $id, $this->actor()));
    }

    public function status(string $id)
    {
        $payload = $this->payload();
        return $this->respond((new KegiatanService())->changeStatus(
            (int) $id,
            (string) ($payload['status'] ?? ''),
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
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['item' => $result['item']], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
