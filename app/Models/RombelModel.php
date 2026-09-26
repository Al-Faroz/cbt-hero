<?php

namespace App\Models;

use CodeIgniter\Model;

class RombelModel extends Model
{
    protected $table = 'rombel';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = ['tingkat', 'kode_rombel', 'display_name', 'status'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
