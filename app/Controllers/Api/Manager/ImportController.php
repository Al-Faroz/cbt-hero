<?php

namespace App\Controllers\Api\Manager;

use App\Controllers\BaseController;
use App\Services\PermissionService;
use App\Services\PesertaImportService;
use App\Services\QuestionImportService;
use App\Traits\ApiResponseTrait;
use Config\Database;

class ImportController extends BaseController
{
    use ApiResponseTrait;

    public function upload()
    {
        $type = (string) $this->request->getPost('import_type');
        if ($type === 'PESERTA') {
            if (!$this->allowed('master.data.manage')) return $this->forbidden();
            return $this->respond((new PesertaImportService())->upload($this->request->getFile('file'), $this->actor()['user_id']));
        }
        if (!in_array($type, ['BANK_WORD', 'BANK_EXCEL'], true)) return $this->apiError('VALIDATION_FAILED', 'Tipe impor tidak dikenal.', 422);
        if (!$this->allowed('master.exam.manage')) return $this->forbidden();
        $context = $this->request->getPost('context_id');
        $bankId = is_scalar($context) ? filter_var($context, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($this->request->getPost('context_type') !== 'bank_soal' || !is_int($bankId))
            return $this->apiError('VALIDATION_FAILED', 'Pilih konteks Bank Soal.', 422);
        $file = $this->request->getFile('file');
        $ext = strtolower(pathinfo((string) ($file?->getClientName() ?? ''), PATHINFO_EXTENSION));
        if (($type === 'BANK_WORD' && $ext !== 'docx') || ($type === 'BANK_EXCEL' && $ext !== 'xlsx'))
            return $this->apiError('VALIDATION_FAILED', 'Jenis file tidak sesuai tipe impor.', 422);
        return $this->respond((new QuestionImportService())->upload($bankId, $file, $this->actor()));
    }

    public function show(string $id) {return $this->dispatch($id, fn($s,$b)=>$b ? $s->job($b,(int)$id) : $s->job((int)$id));}
    public function items(string $id) {return $this->dispatch($id, fn($s,$b)=>$b ? $s->job($b,(int)$id) : $s->items((int)$id,$this->request->getGet()));}
    public function parse(string $id) {return $this->dispatch($id, fn($s,$b)=>$b ? $s->job($b,(int)$id) : $s->parse((int)$id));}
    public function validateJob(string $id) {return $this->dispatch($id, fn($s,$b)=>$b ? $s->validate($b,(int)$id) : $s->validate((int)$id));}
    public function fix(string $id, string $itemId) {
        $json=$this->request->getJSON(true); $payload=is_array($json)?$json:[];
        return $this->dispatch($id, fn($s,$b)=>$b ? $s->change($b,(int)$id,(int)$itemId,'FIX',$payload)
            : $s->changeItem((int)$id,(int)$itemId,'FIX',$payload));
    }
    public function exclude(string $id, string $itemId) {
        return $this->dispatch($id, fn($s,$b)=>$b ? $s->change($b,(int)$id,(int)$itemId,'EXCLUDE',[])
            : $s->changeItem((int)$id,(int)$itemId,'EXCLUDE',[]));
    }
    public function includeItem(string $id, string $itemId) {
        return $this->dispatch($id, fn($s,$b)=>$b ? $s->change($b,(int)$id,(int)$itemId,'INCLUDE',[])
            : $s->changeItem((int)$id,(int)$itemId,'INCLUDE',[]));
    }
    public function commit(string $id) {
        return $this->dispatch($id, fn($s,$b)=>$b ? $s->commit($b,(int)$id,$this->actor(),(string)$this->request->getHeaderLine('Idempotency-Key'))
            : $s->commit((int)$id,(string)$this->request->getHeaderLine('Idempotency-Key'),$this->actor()));
    }

    private function dispatch(string $id, callable $action)
    {
        $row=Database::connect()->table('import_jobs')->select('import_type, context_type, context_id')
            ->where('id',(int)$id)->get()->getRowArray();
        if ($row===null) return $this->apiError('NOT_FOUND','Job tidak ditemukan.',404);
        if ($row['import_type']==='PESERTA') {
            if (!$this->allowed('master.data.manage')) return $this->forbidden();
            return $this->respond($action(new PesertaImportService(), null));
        }
        if (!in_array($row['import_type'],['BANK_WORD','BANK_EXCEL'],true) || $row['context_type']!=='bank_soal')
            return $this->apiError('NOT_FOUND','Job tidak tersedia.',404);
        if (!$this->allowed('master.exam.manage')) return $this->forbidden();
        return $this->respond($action(new QuestionImportService(),(int)$row['context_id']));
    }

    private function allowed(string $permission): bool
    {
        return (new PermissionService())->hasPermission((string)(session()->get('manager_auth')['role'] ?? ''),$permission);
    }

    private function forbidden()
    {
        return $this->apiError('FORBIDDEN','Anda tidak memiliki izin untuk impor ini.',403);
    }

    private function actor(): array
    {
        return ['user_id'=>(int)(session()->get('manager_auth')['user_id'] ?? 0),
            'ip'=>$this->request->getIPAddress(),'agent'=>(string)$this->request->getUserAgent()];
    }

    private function respond(array $result)
    {
        $this->response->setHeader('Cache-Control','no-store, private');
        if (!($result['ok'] ?? false)) return $this->apiError($result['code'],$result['message'],$result['status']);
        if (isset($result['data'])) return $this->apiSuccess($result['data'],$result['status']);
        $data=[];
        foreach (['job','items','pagination','replayed'] as $key) if (array_key_exists($key,$result)) $data[$key]=$result[$key];
        return $this->apiSuccess($data,$result['status']);
    }
}
