<?php

namespace App\Controllers\Participant;

use App\Controllers\BaseController;

class ParticipantExamController extends BaseController
{
    public function index()
    {
        return view('participant/exam/index', [
            'auth' => session()->get('participant_auth'),
        ]);
    }
}
