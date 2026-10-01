<?php

namespace App\Controllers\Manager\Report;

use App\Controllers\BaseController;
use App\Services\ReportExportService;

class ReportExportController extends BaseController
{
    public function xlsx(string $kind)
    {
        $result = (new ReportExportService())->xlsx($kind, $this->request->getGet());
        if (!($result['ok'] ?? false)) {
            return $this->response
                ->setStatusCode((int) ($result['status'] ?? 400))
                ->setBody((string) ($result['message'] ?? 'Export tidak tersedia.'));
        }

        $data = $result['data'];
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $data['filename'] . '"')
            ->setHeader('Cache-Control', 'no-store, private')
            ->setBody($data['content']);
    }

    public function pdf(string $kind)
    {
        $result = (new ReportExportService())->printable($kind, $this->request->getGet());
        if (!($result['ok'] ?? false)) {
            return $this->response
                ->setStatusCode((int) ($result['status'] ?? 400))
                ->setBody((string) ($result['message'] ?? 'Laporan tidak tersedia.'));
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(view('manager/report/print', [
                'report' => $result['data'],
                'pageTitle' => $result['data']['title'] ?? 'Laporan',
            ]));
    }
}
