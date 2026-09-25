<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'actor_realm',
        'actor_id',
        'action',
        'module',
        'entity_type',
        'entity_id',
        'description',
        'before_json',
        'after_json',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $useTimestamps = false;
}
