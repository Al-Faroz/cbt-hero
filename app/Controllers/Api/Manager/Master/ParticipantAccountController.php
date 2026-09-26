<?php

namespace App\Controllers\Api\Manager\Master;

use App\Controllers\BaseController;
use App\Services\ParticipantCredentialService;
use App\Traits\ApiResponseTrait;

class ParticipantAccountController extends BaseController
{
    use ApiResponseTrait;

    public function show(string $id)
    {
        return $this->respond((new ParticipantCredentialService())->account((int) $id));
    }

    public function printable(string $id)
    {
        return $this->respond((new ParticipantCredentialService())->printable((int) $id));
    }

    public function generateUsername(string $id)
    {
        $payload = $this->payload();
        return $this->respond((new ParticipantCredentialService())->username(
            (int) $id, null, ($payload['replace'] ?? false) === true, $this->actor()
        ));
    }

    public function setUsername(string $id)
    {
        $payload = $this->payload();
        $username = $payload['username'] ?? null;
        return $this->respond((new ParticipantCredentialService())->username(
            (int) $id, is_string($username) ? $username : '', true, $this->actor()
        ));
    }

    public function resetPassword(string $id)
    {
        $payload = $this->payload();
        $password = $payload['password'] ?? null;
        return $this->respond((new ParticipantCredentialService())->resetPassword(
            (int) $id, is_string($password) ? $password : ($password === null ? null : ''), $this->actor()
        ));
    }

    public function bulkUsername()
    {
        return $this->bulk('GENERATE_MISSING_USERNAMES');
    }

    public function bulkRegenerateUsername()
    {
        return $this->bulk('REGENERATE_USERNAMES');
    }

    public function bulkResetPassword()
    {
        return $this->bulk('RESET_PASSWORDS');
    }

    private function bulk(string $action)
    {
        $result = (new ParticipantCredentialService())->bulk(
            $this->payload(), $action,
            (string) $this->request->getHeaderLine('Idempotency-Key'), $this->actor()
        );
        $this->response->setHeader('Cache-Control', 'no-store, private');
        if (($result['ok'] ?? false) !== true) {
            return $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
        }
        return $this->apiSuccess([
            'affected' => $result['affected'],
            'selected' => $result['selected'] ?? null,
            'replayed' => $result['replayed'] ?? false,
        ]);
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : (array) $this->request->getPost();
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
        if (($result['ok'] ?? false) !== true) {
            return $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
        }
        if (isset($result['printable'])) {
            return $this->apiSuccess(['printable' => $result['printable']]);
        }
        $data = ['account' => $result['account']];
        if (isset($result['password'])) {
            $data['password'] = $result['password'];
        }
        return $this->apiSuccess($data, $result['status']);
    }
}
