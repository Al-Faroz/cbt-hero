<?php

namespace App\Models;

use CodeIgniter\Model;

class KegiatanModel extends Model
{
    protected $table = 'kegiatan';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'nama', 'jenis', 'tahun_pelajaran', 'semester', 'keterangan',
        'exam_browser_required', 'status', 'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
