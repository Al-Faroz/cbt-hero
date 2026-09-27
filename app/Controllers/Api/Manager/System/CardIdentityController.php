<?php

namespace App\Controllers\Api\Manager\System;

use App\Controllers\BaseController;
use App\Services\CardIdentitySettingsService;
use App\Traits\ApiResponseTrait;

class CardIdentityController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return $this->apiSuccess(['settings' => (new CardIdentitySettingsService())->read()]);
    }

    public function update()
    {
        $service = new CardIdentitySettingsService();
        $auth = session()->get('manager_auth');
        $result = $service->save($this->request->getPost(), $this->request->getFile('logo'), [
            'user_id' => (int) ($auth['user_id'] ?? 0), 'ip' => $this->request->getIPAddress(),
            'agent' => (string) $this->request->getUserAgent(),
        ]);
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['settings' => $result['settings']])
            : $this->apiError($result['code'], $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
