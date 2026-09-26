<?php

namespace App\Controllers\Api\Manager\Master;

use App\Controllers\BaseController;
use App\Services\MataPelajaranService;
use App\Traits\ApiResponseTrait;

class MataPelajaranController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->apiSuccess((new MataPelajaranService())->list($this->request->getGet()));
    }

    public function show(string $id)
    {
        $item = (new MataPelajaranService())->find((int) $id);
        return $item === null
            ? $this->apiError('NOT_FOUND', 'Mata Pelajaran tidak ditemukan.', 404)
            : $this->apiSuccess(['item' => $item]);
    }

    public function create()
    {
        return $this->respond((new MataPelajaranService())->save($this->payload(), null, $this->actor()));
    }

    public function update(string $id)
    {
        return $this->respond((new MataPelajaranService())->save($this->payload(), (int) $id, $this->actor()));
    }

    public function status(string $id)
    {
        return $this->respond((new MataPelajaranService())->changeStatus((int) $id, $this->payload(), $this->actor()));
    }

    public function remove(string $id)
    {
        return $this->respond((new MataPelajaranService())->delete((int) $id, $this->actor()));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }
        $raw = $this->request->getRawInput();
        return is_array($raw) && $raw !== [] ? $raw : (array) $this->request->getPost();
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
        if (($result['ok'] ?? false) !== true) {
            return $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
        }
        return $this->apiSuccess(['item' => $result['item']], $result['status']);
    }
}
