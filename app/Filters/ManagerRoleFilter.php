<?php

namespace App\Filters;

use App\Services\PermissionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ManagerRoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $arguments = is_array($arguments) ? $arguments : [];

        $permission = (string) ($arguments[0] ?? '');
        $isApi = in_array('api', $arguments, true);

        $auth = session()->get('manager_auth');

        if (
            ! is_array($auth)
            || ($auth['logged_in'] ?? false) !== true
            || ! isset($auth['user_id'])
        ) {
            return $this->unauthenticated($isApi);
        }

        if ($permission === '') {
            return $this->forbidden($isApi);
        }

        $permissionService = new PermissionService();

        if (! $permissionService->hasPermission(
            (string) ($auth['role'] ?? ''),
            $permission
        )) {
            return $this->forbidden($isApi);
        }

        return null;
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ) {
        return null;
    }

    private function unauthenticated(bool $isApi)
    {
        if ($isApi) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'ok' => false,
                    'error' => [
                        'code' => 'AUTH_REQUIRED',
                        'message' => 'Session Manager tidak tersedia.',
                    ],
                    'meta' => [
                        'request_id' => bin2hex(random_bytes(8)),
                        'server_time' => date(DATE_ATOM),
                    ],
                ]);
        }

        return redirect()->to(base_url('manager'));
    }

    private function forbidden(bool $isApi)
    {
        if ($isApi) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'ok' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Anda tidak memiliki izin untuk fungsi ini.',
                    ],
                    'meta' => [
                        'request_id' => bin2hex(random_bytes(8)),
                        'server_time' => date(DATE_ATOM),
                    ],
                ]);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('manager/errors/forbidden', [
                'auth' => session()->get('manager_auth') ?? [],
                'pageTitle' => 'Akses Ditolak',
                'pageSubtitle' => 'Permission Manager',
                'activeMenu' => '',
            ]));
    }
}
