<?php

namespace App\Controllers\Manager\Scoring;

use App\Controllers\BaseController;

class LiveEditController extends BaseController
{
    public function question(string $bankId, string $questionId)
    {
        return redirect()->to(
            base_url('manager/master-ujian/bank-soal/' . (int) $bankId . '/soal')
            . '?live=' . (int) $questionId
        );
    }
}
