<?php

namespace App\Controllers\Participant;

use App\Controllers\BaseController;
use App\Services\ParticipantAuthService;
use App\Traits\ApiResponseTrait;

class ParticipantAuthController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $auth = session()->get('participant_auth');

        if (is_array($auth)) {
            $participant = (new ParticipantAuthService())
                ->validateSession($auth);

            if ($participant !== null) {
                return redirect()->to(base_url('ujian'));
            }

            session()->remove('participant_auth');
        }

        return view('participant/auth/login', [
            'pageTitle' => 'Peserta Ujian | CBT-HERO',
            'portalLabel' => 'Portal Peserta',
        ]);
    }

    public function login()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $username = (string) ($payload['username'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        $result = (new ParticipantAuthService())->authenticate(
            $username,
            $password,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        if (($result['ok'] ?? false) !== true) {
            return $this->apiError(
                (string) ($result['code'] ?? 'INVALID_CREDENTIALS'),
                (string) ($result['message'] ?? 'Username atau password tidak sesuai.'),
                (int) ($result['status'] ?? 401)
            );
        }

        /*
         * Session fixation protection.
         * Data realm Manager lain di session yang sama tetap dipertahankan.
         */
        session()->regenerate(true);

        $participant = $result['participant'];

        session()->set('participant_auth', [
            'logged_in' => true,
            'peserta_id' => (int) $participant['id'],
            'username' => (string) $participant['username'],
            'login_at' => date(DATE_ATOM),
        ]);

        return $this->apiSuccess([
            'redirect' => base_url('ujian'),
        ]);
    }

    public function logout()
    {
        /*
         * Logout Participant tidak menyentuh Attempt ACTIVE.
         * Timer/Attempt lifecycle tetap ditentukan engine Attempt.
         */
        session()->remove('participant_auth');
        session()->regenerate(true);

        return $this->apiSuccess([
            'redirect' => base_url('/'),
        ]);
    }

    public function sessionInfo()
    {
        $auth = session()->get('participant_auth');
        $participantId = (int) $auth['peserta_id'];

        $activeAttemptId = (new ParticipantAuthService())
            ->activeAttemptId($participantId);

        return $this->apiSuccess([
            'logged_in' => true,
            'username' => (string) $auth['username'],
            'has_active_attempt' => $activeAttemptId !== null,
            'active_attempt_id' => $activeAttemptId,
        ]);
    }
}
