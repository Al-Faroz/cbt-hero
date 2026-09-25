<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ManagerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = session()->get('manager_auth');

        $valid = is_array($auth)
            && ($auth['logged_in'] ?? false) === true
            && isset($auth['user_id'])
            && in_array($auth['role'] ?? '', ['ADMIN', 'OPERATOR'], true);

        if ($valid) {
            return null;
        }

        if (is_array($arguments) && in_array('api', $arguments, true)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'ok' => false,
                    'error' => [
                        'code'    => 'AUTH_REQUIRED',
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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
