<?php

namespace App\Models;

use CodeIgniter\Model;

class ManagerUserModel extends Model
{
    protected $table            = 'manager_users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'username',
        'nama',
        'password_hash',
        'role',
        'status',
        'failed_login_count',
        'locked_until',
        'last_login_at',
    ];

    protected $useTimestamps = false;
}
