<?php

namespace App\Controllers\Api\Participant;

use App\Controllers\BaseController;
use App\Services\ParticipantExamDiscoveryService;
use App\Traits\ApiResponseTrait;

class ExamDiscoveryController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $auth = session()->get('participant_auth');

        $result = (new ParticipantExamDiscoveryService())
            ->listForParticipant((int) $auth['peserta_id']);

        return $this->apiSuccess($result);
    }

    public function confirmation(int $scheduleId)
    {
        $auth = session()->get('participant_auth');

        $result = (new ParticipantExamDiscoveryService())
            ->confirmationData(
                (int) $auth['peserta_id'],
                $scheduleId
            );

        if ($result === null) {
            return $this->apiError(
                'NOT_FOUND',
                'Ujian tidak tersedia untuk peserta ini.',
                404
            );
        }

        return $this->apiSuccess($result);
    }
}
