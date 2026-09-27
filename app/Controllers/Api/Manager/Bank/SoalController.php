<?php

namespace App\Controllers\Api\Manager\Bank;

use App\Controllers\BaseController;
use App\Services\QuestionService;
use App\Services\AdvancedQuestionService;
use App\Traits\ApiResponseTrait;

class SoalController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $bankId)
    {
        return $this->respond((new QuestionService())->list((int) $bankId, $this->request->getGet()));
    }

    public function show(string $bankId, string $id)
    {
        return $this->respond((new QuestionService())->show((int) $bankId, (int) $id));
    }

    public function create(string $bankId)
    {
        $payload = $this->payload();
        $service = ($payload['question_type'] ?? 'PG') === 'PG' ? new QuestionService() : new AdvancedQuestionService();
        return $this->respond($service->save((int) $bankId, null, $payload, $this->actor()));
    }

    public function update(string $bankId, string $id)
    {
        $payload = $this->payload();
        $service = ($payload['question_type'] ?? 'PG') === 'PG' ? new QuestionService() : new AdvancedQuestionService();
        return $this->respond($service->save((int) $bankId, (int) $id, $payload, $this->actor()));
    }

    public function remove(string $bankId, string $id)
    {
        return $this->respond((new QuestionService())->delete((int) $bankId, (int) $id, $this->actor()));
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
            'ip' => $this->request->getIPAddress(), 'agent' => (string) $this->request->getUserAgent(),
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
