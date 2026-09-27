<?php

namespace App\Models;

use CodeIgniter\Model;

class RuangModel extends Model
{
    protected $table = 'ruang';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = ['kode', 'nama', 'status'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
