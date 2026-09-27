<?php

namespace App\Controllers\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\KartuUjianService;

class KartuUjianController extends BaseController
{
    public function index(string $id)
    {
        $data = (new KartuUjianService())->prepare((int) $id, $this->request->getGet());
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
        if ($data['status'] !== 200) {
            return $this->response->setStatusCode($data['status'])
                ->setBody(esc($data['message']));
        }
        return view('manager/exam/kartu/index', $data);
    }
}
