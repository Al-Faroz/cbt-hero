<?php

namespace App\Models;

use CodeIgniter\Model;

class MataPelajaranModel extends Model
{
    protected $table = 'mata_pelajaran';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = ['kode_mapel', 'nama_mapel', 'singkatan', 'status', 'urutan'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
