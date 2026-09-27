<?php

namespace App\Controllers\Api\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\NomorPesertaService;
use App\Traits\ApiResponseTrait;

class NomorPesertaController extends BaseController
{
    use ApiResponseTrait;

    public function generate(string $id)
    {
        $json = $this->request->getJSON(true);
        $result = (new NomorPesertaService())->generate((int) $id, is_array($json) ? $json : [], [
            'user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
            'ip' => $this->request->getIPAddress(),
            'agent' => (string) $this->request->getUserAgent(),
        ]);
        if (($result['ok'] ?? false) !== true)
            return $this->apiError($result['code'], $result['message'], $result['status']);
        unset($result['ok'], $result['status']);
        return $this->apiSuccess($result);
    }
}
