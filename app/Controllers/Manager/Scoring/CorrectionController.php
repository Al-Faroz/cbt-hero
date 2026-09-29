<?php

namespace App\Controllers\Manager\Scoring;

use App\Controllers\BaseController;

class CorrectionController extends BaseController
{
    public function index()
    {
        return redirect()->to(base_url('manager/pelaksanaan/penilaian'));
    }
}
