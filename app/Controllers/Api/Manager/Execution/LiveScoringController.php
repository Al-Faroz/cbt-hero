<?php

namespace App\Controllers\Api\Manager\Execution;

use App\Controllers\BaseController;
use App\Services\LiveScoringService;
use App\Traits\ApiResponseTrait;

class LiveScoringController extends BaseController
{
    use ApiResponseTrait;

    public function state()
    {
        return $this->respond((new LiveScoringService())->state());
    }

    public function options()
    {
        return $this->respond((new LiveScoringService())->options());
    }

    public function start()
    {
        $json = $this->request->getJSON(true);
        return $this->respond((new LiveScoringService())->start(
            (int) (is_array($json) ? ($json['jadwal_id'] ?? 0) : 0),
            $this->actor()
        ));
    }

    public function stop()
    {
        return $this->respond((new LiveScoringService())->stop($this->actor()));
    }

    public function regenerate()
    {
        return $this->respond((new LiveScoringService())->regenerate($this->actor()));
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
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
