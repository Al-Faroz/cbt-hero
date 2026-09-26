<?php

namespace App\Controllers\Api\Manager\Master;

use App\Controllers\BaseController;
use App\Services\PesertaService;
use App\Traits\ApiResponseTrait;

class PesertaController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return $this->apiSuccess((new PesertaService())->list($this->request->getGet()));
    }

    public function show(string $id)
    {
        $item = (new PesertaService())->find((int) $id);
        return $item === null
            ? $this->apiError('NOT_FOUND', 'Peserta tidak ditemukan.', 404)
            : $this->apiSuccess(['item' => $item]);
    }

    public function rombelOptions()
    {
        return $this->apiSuccess(['items' => (new PesertaService())->rombelOptions()]);
    }

    public function create()
    {
        return $this->respond((new PesertaService())->save($this->payload(), null, $this->actor()));
    }

    public function update(string $id)
    {
        return $this->respond((new PesertaService())->save($this->payload(), (int) $id, $this->actor()));
    }

    public function status(string $id)
    {
        return $this->respond((new PesertaService())->changeStatus((int) $id, $this->payload(), $this->actor()));
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
            return $this->apiError(
                $result['code'], $result['message'], $result['status'], $result['fields'] ?? []
            );
        }
        return $this->apiSuccess(['item' => $result['item']], $result['status']);
    }
}
