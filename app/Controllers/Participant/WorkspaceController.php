<?php

namespace App\Controllers\Participant;

use App\Controllers\BaseController;
use App\Services\AttemptOwnershipService;
use App\Services\ParticipantExamDiscoveryService;
use Config\Database;
use CodeIgniter\Exceptions\PageNotFoundException;

class WorkspaceController extends BaseController
{
    public function index(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        $participantId = (int) ($auth['peserta_id'] ?? 0);
        $attempt = (new AttemptOwnershipService())->owned(
            Database::connect(),
            $participantId,
            (int) $attemptId
        );
        if ($attempt === null) throw PageNotFoundException::forPageNotFound('Attempt tidak ditemukan.');
        if (in_array($attempt['status'], ['FINISHED', 'SUPERSEDED'], true))
            return redirect()->to(base_url('attempt/' . (int) $attemptId . '/selesai'));

        $participant = (new ParticipantExamDiscoveryService())->participantIdentity($participantId);
        if ($participant === null) return redirect()->to(base_url('/'));

        return view('participant/attempt/workspace', [
            'auth' => $auth,
            'participant' => $participant,
            'attempt' => $attempt,
            'pageTitle' => 'Mengerjakan Ujian',
        ]);
    }
}
