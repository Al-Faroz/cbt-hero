<?php

namespace App\Controllers\Api\Manager\Scoring;

use App\Controllers\BaseController;
use App\Services\QuestionLiveEditService;
use App\Services\VoidService;
use App\Traits\ApiResponseTrait;

class LiveEditController extends BaseController
{
    use ApiResponseTrait;

    public function revisions(string $bankId, string $questionId)
    {
        return $this->respond((new QuestionLiveEditService())->revisions(
            (int) $bankId,
            (int) $questionId
        ));
    }

    public function revision(string $bankId, string $questionId)
    {
        return $this->respond((new QuestionLiveEditService())->revise(
            (int) $bankId,
            (int) $questionId,
            $this->payload(),
            $this->actor()
        ));
    }

    public function void(string $bankId, string $questionId)
    {
        return $this->respond((new VoidService())->void(
            (int) $bankId,
            (int) $questionId,
            $this->payload(),
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
