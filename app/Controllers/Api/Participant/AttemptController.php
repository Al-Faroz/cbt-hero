<?php

namespace App\Controllers\Api\Participant;

use App\Controllers\BaseController;
use App\Services\AttemptResumeService;
use App\Services\AttemptRuntimeService;
use App\Services\AttemptStartService;
use App\Traits\ApiResponseTrait;

class AttemptController extends BaseController
{
    use ApiResponseTrait;

    public function start(string $jadwalId)
    {
        $auth = session()->get('participant_auth');
        $payload = $this->payload();
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));

        return $this->respond((new AttemptStartService())->start(
            (int) $auth['peserta_id'],
            (int) $jadwalId,
            $payload,
            $key
        ));
    }

    public function resume(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        $payload = $this->payload();
        $key = trim((string) $this->request->getHeaderLine('Idempotency-Key'));

        return $this->respond((new AttemptResumeService())->resume(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            $payload,
            $key
        ));
    }

    public function status(string $attemptId)
    {
        $auth = session()->get('participant_auth');

        return $this->respond((new AttemptRuntimeService())->status(
            (int) $auth['peserta_id'],
            (int) $attemptId,
            $this->clientUuid(),
            $this->clientGeneration()
        ));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : [];
    }

    private function clientUuid(): string
    {
        return trim((string) $this->request->getHeaderLine('X-CBT-Client-Id'));
    }

    private function clientGeneration(): int
    {
        return (int) $this->request->getHeaderLine('X-CBT-Client-Generation');
    }

    private function respond(array $result)
    {
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
