<?php

namespace App\Controllers\Api\Manager\Execution;

use App\Controllers\BaseController;
use App\Services\TokenService;
use App\Traits\ApiResponseTrait;

class TokenController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->respond((new TokenService())->read());
    }

    public function state()
    {
        $payload = $this->payload();
        if (!array_key_exists('enabled', $payload) || !is_bool($payload['enabled']))
            return $this->apiError('VALIDATION_FAILED', 'Status Token tidak valid.', 422);
        return $this->respond((new TokenService())->setState($payload['enabled'], $this->actor()));
    }

    public function generate()
    {
        return $this->respond((new TokenService())->generate($this->actor()));
    }

    public function rotate()
    {
        return $this->respond((new TokenService())->rotate($this->actor()));
    }

    public function autoRotate()
    {
        $payload = $this->payload();
        return $this->respond((new TokenService())->setAutoRotate(
            $payload['auto_rotate_minutes'] ?? null,
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
        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
