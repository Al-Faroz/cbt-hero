<?php

namespace App\Controllers\Api\Manager\Master;

use App\Controllers\BaseController;
use App\Services\PesertaImportService;
use App\Services\PesertaTemplateService;
use App\Traits\ApiResponseTrait;
use Throwable;

class PesertaImportController extends BaseController
{
    use ApiResponseTrait;

    public function template()
    {
        try {
            $bytes = (new PesertaTemplateService())->build();
        } catch (Throwable $e) {
            log_message('error', 'Template Peserta gagal: {message}', ['message' => $e->getMessage()]);
            return service('response')->setStatusCode(503)->setBody('Template tidak tersedia.');
        }
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="template_peserta.xlsx"')
            ->setHeader('Cache-Control', 'no-store, private')
            ->setBody($bytes);
    }

    public function upload()
    {
        if ($this->request->getPost('import_type') !== 'PESERTA') {
            return $this->apiError('VALIDATION_FAILED', 'Import type harus PESERTA.', 422);
        }
        return $this->respond((new PesertaImportService())->upload(
            $this->request->getFile('file'), (int) (session()->get('manager_auth')['user_id'] ?? 0)
        ), 201);
    }

    public function show(string $id)
    {
        return $this->respond((new PesertaImportService())->job((int) $id));
    }

    public function items(string $id)
    {
        return $this->respond((new PesertaImportService())->items((int) $id, $this->request->getGet()));
    }

    public function parse(string $id)
    {
        return $this->respond((new PesertaImportService())->parse((int) $id));
    }

    public function validateJob(string $id)
    {
        return $this->respond((new PesertaImportService())->validate((int) $id));
    }

    public function fix(string $id, string $itemId)
    {
        return $this->respond((new PesertaImportService())->changeItem((int) $id, (int) $itemId, 'FIX', $this->payload()));
    }

    public function exclude(string $id, string $itemId)
    {
        return $this->respond((new PesertaImportService())->changeItem((int) $id, (int) $itemId, 'EXCLUDE', []));
    }

    public function includeItem(string $id, string $itemId)
    {
        return $this->respond((new PesertaImportService())->changeItem((int) $id, (int) $itemId, 'INCLUDE', []));
    }

    public function commit(string $id)
    {
        return $this->respond((new PesertaImportService())->commit(
            (int) $id, (string) $this->request->getHeaderLine('Idempotency-Key'), [
                'user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
                'ip' => $this->request->getIPAddress(),
                'agent' => (string) $this->request->getUserAgent(),
            ]
        ));
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : [];
    }

    private function respond(array $result, int $successStatus = 200)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        if (($result['ok'] ?? false) !== true) {
            return $this->apiError($result['code'], $result['message'], $result['status']);
        }
        $data = [];
        foreach (['job', 'items', 'pagination', 'replayed'] as $key) {
            if (array_key_exists($key, $result)) {
                $data[$key] = $result[$key];
            }
        }
        return $this->apiSuccess($data, $successStatus);
    }
}
