<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthLoginAttemptModel extends Model
{
    protected $table            = 'auth_login_attempts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'realm',
        'username_input',
        'account_ref_id',
        'ip_address',
        'user_agent',
        'success',
        'failure_reason',
        'attempted_at',
    ];

    protected $useTimestamps = false;
}
