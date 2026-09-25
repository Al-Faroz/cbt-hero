<?php

namespace App\Controllers\Manager\System;

use App\Controllers\BaseController;
use App\Services\ManagerUserService;
use App\Traits\ApiResponseTrait;

class ManagerUserController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return view('manager/system/users/index', [
            'auth' => session()->get('manager_auth'),
            'users' => (new ManagerUserService())->listUsers(),
            'pageTitle' => 'User Manager',
            'pageSubtitle' => 'Kelola account Admin dan Operator',
            'activeMenu' => 'system-users',
        ]);
    }

    public function list()
    {
        return $this->apiSuccess([
            'items' => (new ManagerUserService())->listUsers(),
        ]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $auth = session()->get('manager_auth');

        $result = (new ManagerUserService())->createUser(
            is_array($payload) ? $payload : [],
            (int) $auth['user_id'],
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        if (($result['ok'] ?? false) !== true) {
            return $this->apiError(
                (string) ($result['code'] ?? 'VALIDATION_FAILED'),
                (string) ($result['message'] ?? 'Data Manager belum valid.'),
                (int) ($result['status'] ?? 422),
                is_array($result['fields'] ?? null) ? $result['fields'] : []
            );
        }

        return $this->apiSuccess([
            'user' => $result['user'],
        ], 201);
    }
}
