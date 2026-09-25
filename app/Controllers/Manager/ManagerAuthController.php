<?php

namespace App\Controllers\Manager;

use App\Controllers\BaseController;
use App\Services\AuditService;
use App\Services\ManagerAuthService;
use App\Traits\ApiResponseTrait;

class ManagerAuthController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $auth = session()->get('manager_auth');

        if (is_array($auth) && ($auth['logged_in'] ?? false) === true) {
            return redirect()->to(base_url('manager/dashboard'));
        }

        return view('manager/auth/login');
    }

    public function login()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $username = (string) ($payload['username'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        $service = new ManagerAuthService();

        $result = $service->authenticate(
            $username,
            $password,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        if (($result['ok'] ?? false) !== true) {
            return $this->apiError(
                (string) ($result['code'] ?? 'AUTH_INVALID'),
                (string) ($result['message'] ?? 'Login gagal.'),
                (int) ($result['status'] ?? 401)
            );
        }

        session()->regenerate(true);

        $user = $result['user'];

        session()->set('manager_auth', [
            'logged_in' => true,
            'user_id'   => $user['id'],
            'username'  => $user['username'],
            'nama'      => $user['nama'],
            'role'      => $user['role'],
            'login_at'  => date(DATE_ATOM),
        ]);

        (new AuditService())->log(
            'MANAGER',
            (int) $user['id'],
            'LOGIN',
            'AUTH',
            'Manager login berhasil.',
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        return $this->apiSuccess([
            'redirect' => base_url('manager/dashboard'),
            'user' => [
                'username' => $user['username'],
                'nama'     => $user['nama'],
                'role'     => $user['role'],
            ],
        ]);
    }

    public function logout()
    {
        $auth = session()->get('manager_auth');

        if (is_array($auth) && isset($auth['user_id'])) {
            (new AuditService())->log(
                'MANAGER',
                (int) $auth['user_id'],
                'LOGOUT',
                'AUTH',
                'Manager logout.',
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );
        }

        session()->remove('manager_auth');
        session()->regenerate(true);

        return $this->apiSuccess([
            'redirect' => base_url('manager'),
        ]);
    }

    public function sessionInfo()
    {
        $auth = session()->get('manager_auth');

        return $this->apiSuccess([
            'authenticated' => true,
            'user' => [
                'id'       => (int) $auth['user_id'],
                'username' => $auth['username'],
                'nama'     => $auth['nama'],
                'role'     => $auth['role'],
            ],
        ]);
    }
}
