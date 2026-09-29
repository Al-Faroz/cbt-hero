<?php

namespace App\Controllers\Api\Manager\Scoring;

use App\Controllers\BaseController;
use App\Services\ScoringQueryService;
use App\Traits\ApiResponseTrait;

class ScoringController extends BaseController
{
    use ApiResponseTrait;

    public function attempt(string $attemptId)
    {
        return $this->respond((new ScoringQueryService())->attemptDetail((int) $attemptId));
    }

    public function questions()
    {
        return $this->respond((new ScoringQueryService())->questions($this->request->getGet()));
    }

    public function responses(string $questionId)
    {
        return $this->respond((new ScoringQueryService())->responses(
            (int) $questionId,
            $this->request->getGet()
        ));
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
