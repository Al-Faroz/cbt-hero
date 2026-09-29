<?php

namespace App\Controllers\Api\Manager\Execution;

use App\Controllers\BaseController;
use App\Services\MonitoringService;
use App\Traits\ApiResponseTrait;

class MonitoringController extends BaseController
{
    use ApiResponseTrait;

    public function options()
    {
        return $this->respond((new MonitoringService())->options());
    }

    public function summary()
    {
        return $this->respond((new MonitoringService())->summary($this->request->getGet()));
    }

    public function attempts()
    {
        return $this->respond((new MonitoringService())->attempts($this->request->getGet()));
    }

    public function detail(string $attemptId)
    {
        return $this->respond((new MonitoringService())->detail((int) $attemptId));
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
