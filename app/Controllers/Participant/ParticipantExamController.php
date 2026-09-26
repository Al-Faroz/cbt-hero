<?php

namespace App\Controllers\Participant;

use App\Controllers\BaseController;
use App\Services\ParticipantExamDiscoveryService;

class ParticipantExamController extends BaseController
{
    public function index()
    {
        $auth = session()->get('participant_auth');
        $participantId = (int) $auth['peserta_id'];

        $participant = (new ParticipantExamDiscoveryService())
            ->participantIdentity($participantId);

        if ($participant === null) {
            session()->remove('participant_auth');

            return redirect()->to(base_url('/'));
        }

        return view('participant/exam/index', [
            'auth' => $auth,
            'participant' => $participant,
            'pageTitle' => 'Daftar Ujian',
        ]);
    }

    public function confirmation(int $scheduleId)
    {
        $auth = session()->get('participant_auth');

        $data = (new ParticipantExamDiscoveryService())
            ->confirmationData(
                (int) $auth['peserta_id'],
                $scheduleId
            );

        if ($data === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(
                'Ujian tidak tersedia.'
            );
        }

        return view('participant/exam/confirmation', [
            'auth' => $auth,
            'participant' => $data['participant'],
            'confirmation' => $data,
            'pageTitle' => 'Konfirmasi Ujian',
        ]);
    }
}
