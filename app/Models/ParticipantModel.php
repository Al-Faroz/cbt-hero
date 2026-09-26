<?php

namespace App\Models;

use CodeIgniter\Model;

class ParticipantModel extends Model
{
    protected $table            = 'peserta';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'nisn',
        'nama',
        'jenis_kelamin',
        'rombel_id',
        'status',
        'keterangan',
        'username',
        'password_hash',
        'password_encrypted',
        'credential_status',
        'credential_revision',
        'failed_login_count',
        'locked_until',
        'last_login_at',
        'credential_changed_at',
    ];

    protected $useTimestamps = false;
}
