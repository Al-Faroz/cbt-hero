<?php

namespace App\Filters;

use App\Models\ManagerUserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ManagerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $arguments = is_array($arguments) ? $arguments : [];
        $isApi = in_array('api', $arguments, true);

        $auth = session()->get('manager_auth');

        if (
            ! is_array($auth)
            || ($auth['logged_in'] ?? false) !== true
            || ! isset($auth['user_id'])
        ) {
            return $this->reject($isApi);
        }

        /*
         * Session tidak menjadi sumber authority tunggal.
         * Status dan role Manager dibaca kembali dari database pada protected
         * request sehingga perubahan role/status berlaku tanpa menunggu
         * session lama kedaluwarsa.
         */
        $user = (new ManagerUserModel())
            ->select('id, username, nama, role, status')
            ->find((int) $auth['user_id']);

        $valid = is_array($user)
            && ($user['status'] ?? '') === 'ACTIVE'
            && in_array($user['role'] ?? '', ['ADMIN', 'OPERATOR'], true);

        if (! $valid) {
            session()->remove('manager_auth');

            return $this->reject($isApi);
        }

        /*
         * Sinkronkan identity ringan bila Admin mengubah nama/role account.
         */
        if (
            ($auth['username'] ?? null) !== $user['username']
            || ($auth['nama'] ?? null) !== $user['nama']
            || ($auth['role'] ?? null) !== $user['role']
        ) {
            $auth['username'] = $user['username'];
            $auth['nama'] = $user['nama'];
            $auth['role'] = $user['role'];

            session()->set('manager_auth', $auth);
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

    private function reject(bool $isApi)
    {
        if ($isApi) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'ok' => false,
                    'error' => [
                        'code' => 'AUTH_REQUIRED',
                        'message' => 'Session Manager tidak tersedia atau sudah tidak valid.',
                    ],
                    'meta' => [
                        'request_id' => bin2hex(random_bytes(8)),
                        'server_time' => date(DATE_ATOM),
                    ],
                ]);
        }

        return redirect()->to(base_url('manager'));
    }
}
