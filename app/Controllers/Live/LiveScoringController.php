<?php

namespace App\Controllers\Live;

use App\Controllers\BaseController;
use App\Services\LiveScoringService;
use App\Traits\ApiResponseTrait;
use CodeIgniter\Exceptions\PageNotFoundException;

class LiveScoringController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $token)
    {
        $result = (new LiveScoringService())->publicState($token);
        if (!($result['ok'] ?? false)) {
            throw PageNotFoundException::forPageNotFound('Live Scoring tidak tersedia.');
        }

        return view('public/live_scoring', [
            'token' => $token,
            'initial' => $result['data'],
            'snapshotUrl' => base_url('api/live/' . rawurlencode($token)),
        ]);
    }

    public function snapshot(string $token)
    {
        $result = (new LiveScoringService())->publicState($token);
        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache');

        return ($result['ok'] ?? false)
            ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status']);
    }
}
