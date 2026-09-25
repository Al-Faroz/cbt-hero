<?php

namespace App\Filters;

use App\Services\ParticipantAuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ParticipantAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $arguments = is_array($arguments) ? $arguments : [];
        $isApi = in_array('api', $arguments, true);

        $auth = session()->get('participant_auth');

        if (! is_array($auth)) {
            return $this->reject($isApi);
        }

        $participant = (new ParticipantAuthService())
            ->validateSession($auth);

        if ($participant === null) {
            session()->remove('participant_auth');

            return $this->reject($isApi);
        }

        /*
         * Username boleh dipakai UI, tetapi DB tetap authority.
         * Sinkronkan session bila username berubah sebelum lifecycle menguncinya.
         */
        if (($auth['username'] ?? null) !== $participant['username']) {
            $auth['username'] = $participant['username'];
            session()->set('participant_auth', $auth);
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
                        'message' => 'Session Peserta tidak tersedia atau sudah tidak valid.',
                    ],
                    'meta' => [
                        'request_id' => bin2hex(random_bytes(8)),
                        'server_time' => date(DATE_ATOM),
                    ],
                ]);
        }

        return redirect()->to(base_url('/'));
    }
}
