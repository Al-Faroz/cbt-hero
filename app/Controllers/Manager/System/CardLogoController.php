<?php

namespace App\Controllers\Manager\System;

use App\Controllers\BaseController;
use App\Services\CardIdentitySettingsService;

class CardLogoController extends BaseController
{
    public function show()
    {
        $path = (new CardIdentitySettingsService())->logoPath();
        if ($path === null) return $this->response->setStatusCode(404);
        $content = file_get_contents($path);
        if ($content === false) return $this->response->setStatusCode(503);
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';
        return $this->response->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($content);
    }
}
