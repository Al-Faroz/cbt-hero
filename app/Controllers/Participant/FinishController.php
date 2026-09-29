<?php

namespace App\Controllers\Participant;

use App\Controllers\BaseController;
use App\Services\AttemptOwnershipService;
use App\Services\ParticipantExamDiscoveryService;
use Config\Database;
use CodeIgniter\Exceptions\PageNotFoundException;

class FinishController extends BaseController
{
    public function index(string $attemptId)
    {
        $auth = session()->get('participant_auth');
        $participantId = (int) ($auth['peserta_id'] ?? 0);
        $db = Database::connect();
        $attempt = (new AttemptOwnershipService())->owned($db, $participantId, (int) $attemptId);
        if ($attempt === null) throw PageNotFoundException::forPageNotFound('Attempt tidak ditemukan.');
        if ($attempt['status'] === 'ACTIVE')
            return redirect()->to(base_url('attempt/' . (int) $attemptId));

        $participant = (new ParticipantExamDiscoveryService())->participantIdentity($participantId);
        if ($participant === null) return redirect()->to(base_url('/'));

        $snapshot = $db->table('result_snapshot')
            ->where('attempt_id', (int) $attemptId)
            ->orderBy('snapshot_version', 'DESC')->get()->getRowArray();
        $showResult = (int) $attempt['tampilkan_nilai_saat_selesai'] === 1
            && $attempt['psych_instrument_id'] === null;

        return view('participant/attempt/finished', [
            'auth' => $auth,
            'participant' => $participant,
            'attempt' => $attempt,
            'snapshot' => $snapshot,
            'showResult' => $showResult,
            'pageTitle' => 'Ujian Selesai',
        ]);
    }
}
