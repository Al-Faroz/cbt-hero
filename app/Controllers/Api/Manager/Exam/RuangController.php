<?php

namespace App\Controllers\Api\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\RuangAssignmentService;
use App\Services\RuangService;
use App\Traits\ApiResponseTrait;

class RuangController extends BaseController
{
    use ApiResponseTrait;

    public function index() { return $this->apiSuccess((new RuangService())->list($this->request->getGet())); }
    public function options() { return $this->apiSuccess((new RuangService())->options()); }
    public function create() { return $this->respond((new RuangService())->save($this->payload(), null, $this->actor())); }
    public function update(string $id) { return $this->respond((new RuangService())->save($this->payload(), (int) $id, $this->actor())); }
    public function status(string $id) { return $this->respond((new RuangService())->changeStatus((int) $id, $this->payload(), $this->actor())); }
    public function remove(string $id) { return $this->respond((new RuangService())->delete((int) $id, $this->actor())); }
    public function assign(string $id) { return $this->respond((new RuangAssignmentService())->assign((int) $id, $this->payload(), $this->actor())); }
    public function assignIndividual(string $id, string $memberId)
    {
        return $this->respond((new RuangAssignmentService())->assign((int) $id,
            ['scope' => 'IDS', 'value' => [(int) $memberId]] + $this->payload(), $this->actor()));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : [];
    }

    private function actor(): array
    {
        return ['user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
            'ip' => $this->request->getIPAddress(), 'agent' => (string) $this->request->getUserAgent()];
    }

    private function respond(array $result)
    {
        if (($result['ok'] ?? false) !== true)
            return $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
        $status = $result['status']; unset($result['ok'], $result['status']);
        return $this->apiSuccess($result, $status);
    }
}
