<?php

namespace App\Controllers\Api\Manager\Report;

use App\Controllers\BaseController;
use App\Services\AnalisisSoalService;
use App\Traits\ApiResponseTrait;

class AnalisisSoalController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->respond((new AnalisisSoalService())->index($this->request->getGet()));
    }

    public function show(string $questionId)
    {
        return $this->respond((new AnalisisSoalService())->show((int) $questionId, $this->request->getGet()));
    }

    public function options()
    {
        return $this->respond((new AnalisisSoalService())->options());
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
