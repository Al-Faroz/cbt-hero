<?php

namespace App\Services;

use App\Models\AuditLogModel;

class AuditService
{
    public function log(
        string $actorRealm,
        ?int $actorId,
        string $action,
        string $module,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $model = new AuditLogModel();

        $model->insert([
            'actor_realm' => $actorRealm,
            'actor_id'    => $actorId,
            'action'      => $action,
            'module'      => $module,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'before_json' => $before === null
                ? null
                : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_json'  => $after === null
                ? null
                : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
