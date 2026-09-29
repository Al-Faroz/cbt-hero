<?php

namespace App\Controllers\Api\Manager\Execution;

use App\Controllers\BaseController;
use App\Services\ForceFinishService;
use App\Services\ResetAccessService;
use App\Services\TimeAdjustmentService;
use App\Traits\ApiResponseTrait;

class AttemptControlController extends BaseController
{
    use ApiResponseTrait;

    public function resetAccess(string $attemptId = '')
    {
        $payload = $this->payload();
        $ids = $attemptId !== '' ? [(int) $attemptId] : ($payload['attempt_ids'] ?? []);
        return $this->respond((new ResetAccessService())->execute(
            is_array($ids) ? $ids : [],
            is_scalar($payload['reason'] ?? null) ? (string) $payload['reason'] : '',
            $this->key(),
            $this->actor()
        ));
    }

    public function addTime(string $attemptId = '')
    {
        $payload = $this->payload();
        $ids = $attemptId !== '' ? [(int) $attemptId] : ($payload['attempt_ids'] ?? []);
        $seconds = is_scalar($payload['seconds_added'] ?? null) ? (int) $payload['seconds_added'] : 0;
        return $this->respond((new TimeAdjustmentService())->execute(
            is_array($ids) ? $ids : [],
            $seconds,
            is_scalar($payload['reason'] ?? null) ? (string) $payload['reason'] : '',
            $this->key(),
            $this->actor()
        ));
    }

    public function forceFinish(string $attemptId = '')
    {
        $payload = $this->payload();
        $ids = $attemptId !== '' ? [(int) $attemptId] : ($payload['attempt_ids'] ?? []);
        return $this->respond((new ForceFinishService())->execute(
            is_array($ids) ? $ids : [],
            is_scalar($payload['reason'] ?? null) ? (string) $payload['reason'] : '',
            ($payload['confirm_pending_sync_risk'] ?? false) === true,
            $this->key(),
            $this->actor()
        ));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : [];
    }

    private function key(): string
    {
        return trim((string) $this->request->getHeaderLine('Idempotency-Key'));
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
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
