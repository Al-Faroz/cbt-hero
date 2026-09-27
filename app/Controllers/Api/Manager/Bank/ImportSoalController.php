<?php

namespace App\Controllers\Api\Manager\Bank;

use App\Controllers\BaseController;
use App\Services\QuestionImportService;
use App\Traits\ApiResponseTrait;

class ImportSoalController extends BaseController
{
    use ApiResponseTrait;

    public function index(string $bankId)
    {
        return $this->respond((new QuestionImportService())->jobs((int) $bankId));
    }

    public function template(string $format)
    {
        if (!in_array($format, ['xlsx', 'docx'], true)) return $this->apiError('NOT_FOUND', 'Template tidak ditemukan.', 404);
        $path = ROOTPATH . 'assets/templates/bank_soal_akademik.' . $format;
        if (!is_file($path)) return $this->apiError('NOT_FOUND', 'Template tidak tersedia.', 404);
        $mime = $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        return $this->response->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="template_bank_soal_akademik.' . $format . '"')
            ->setHeader('Cache-Control', 'no-store, private')->setBody(file_get_contents($path));
    }

    public function upload(string $bankId)
    {
        return $this->respond((new QuestionImportService())->upload((int) $bankId, $this->request->getFile('file'), $this->actor()));
    }

    public function show(string $bankId, string $id)
    {
        return $this->respond((new QuestionImportService())->job((int) $bankId, (int) $id));
    }

    public function validateJob(string $bankId, string $id)
    {
        return $this->respond((new QuestionImportService())->validate((int) $bankId, (int) $id));
    }

    public function change(string $bankId, string $id, string $itemId, string $action)
    {
        $json = $this->request->getJSON(true);
        return $this->respond((new QuestionImportService())->change((int) $bankId, (int) $id, (int) $itemId,
            $action, is_array($json) ? $json : []));
    }

    public function commit(string $bankId, string $id)
    {
        return $this->respond((new QuestionImportService())->commit((int) $bankId, (int) $id,
            $this->actor(), (string) $this->request->getHeaderLine('Idempotency-Key')));
    }

    private function actor(): array
    {
        return ['user_id' => (int) (session()->get('manager_auth')['user_id'] ?? 0),
            'ip' => $this->request->getIPAddress(), 'agent' => (string) $this->request->getUserAgent()];
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return ($result['ok'] ?? false) ? $this->apiSuccess($result['data'], $result['status'])
            : $this->apiError($result['code'], $result['message'], $result['status']);
    }
}
