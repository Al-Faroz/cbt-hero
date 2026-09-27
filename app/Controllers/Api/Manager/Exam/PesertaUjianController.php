<?php

namespace App\Controllers\Api\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\PesertaKegiatanService;
use App\Traits\ApiResponseTrait;

class PesertaUjianController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $id)
    {
        return $this->respond((new PesertaKegiatanService())->list((int) $id, $this->request->getGet()));
    }

    public function candidates(string $id)
    {
        return $this->respond((new PesertaKegiatanService())->candidates((int) $id, $this->request->getGet()));
    }

    public function assign(string $id)
    {
        $json = $this->request->getJSON(true);
        return $this->respond((new PesertaKegiatanService())->assign((int) $id,
            is_array($json) ? $json : [], $this->actor()));
    }

    public function remove(string $id, string $membershipId)
    {
        return $this->respond((new PesertaKegiatanService())->remove((int) $id,
            (int) $membershipId, $this->actor()));
    }

    public function bulkRemove(string $id)
    {
        $json = $this->request->getJSON(true);
        return $this->respond((new PesertaKegiatanService())->bulkRemove((int) $id,
            is_array($json) ? $json : [], $this->actor()));
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
            return $this->apiError($result['code'], $result['message'], $result['status']);
        }
        unset($result['ok'], $result['status']);
        return $this->apiSuccess($result);
    }
}
