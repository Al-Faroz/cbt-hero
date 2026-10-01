<?php

namespace App\Controllers\Api\Manager\Report;

use App\Controllers\BaseController;
use App\Services\RekapNilaiService;
use App\Traits\ApiResponseTrait;

class RekapNilaiController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->respond((new RekapNilaiService())->matrix($this->request->getGet()));
    }

    public function options()
    {
        return $this->respond((new RekapNilaiService())->options());
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
