<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class PrintController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/bank/print', ['bankId' => (int) $id]);
    }
}
