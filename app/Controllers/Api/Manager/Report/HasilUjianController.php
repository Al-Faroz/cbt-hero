<?php

namespace App\Controllers\Api\Manager\Report;

use App\Controllers\BaseController;
use App\Services\HasilUjianService;
use App\Traits\ApiResponseTrait;

class HasilUjianController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->respond((new HasilUjianService())->index($this->request->getGet()));
    }

    public function show(string $snapshotId)
    {
        return $this->respond((new HasilUjianService())->show((int) $snapshotId));
    }

    public function options()
    {
        return $this->respond((new HasilUjianService())->options());
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');

        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError(
                $result['code'],
                $result['message'],
                $result['status'],
                $result['fields'] ?? []
            );
    }
}
