<?php

namespace App\Controllers\Api\Manager\System;

use App\Controllers\BaseController;
use App\Services\AcademicSettingsService;
use App\Traits\ApiResponseTrait;

class SettingsController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->apiSuccess(['settings' => (new AcademicSettingsService())->read()]);
    }

    public function update()
    {
        $payload = $this->request->getJSON(true);
        $auth = session()->get('manager_auth');
        $result = (new AcademicSettingsService())->save(is_array($payload) ? $payload : [], [
            'user_id' => (int) ($auth['user_id'] ?? 0),
            'ip' => $this->request->getIPAddress(),
            'agent' => (string) $this->request->getUserAgent(),
        ]);
        return ($result['ok'] ?? false)
            ? $this->apiSuccess(['settings' => $result['settings']])
            : $this->apiError('VALIDATION_FAILED', $result['message'], $result['status'], $result['fields'] ?? []);
    }
}
