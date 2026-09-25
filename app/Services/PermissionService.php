<?php

namespace App\Services;

class PermissionService
{
    public const DASHBOARD = 'manager.dashboard';
    public const MASTER_DATA = 'master.data.manage';
    public const MASTER_UJIAN = 'master.exam.manage';
    public const EXECUTION = 'execution.manage';
    public const RESULTS = 'results.manage';
    public const LIVE_SCORING = 'live-scoring.manage';
    public const BACKUP_CREATE = 'system.backup.create';
    public const LOG_VIEW = 'system.logs.view';

    public const USERS_MANAGE = 'system.users.manage';
    public const SETTINGS_MANAGE = 'system.settings.manage';
    public const RESTORE = 'system.restore';
    public const CLEAR_DATA = 'system.clear-data';
    public const LOG_MANAGE = 'system.logs.manage';
    public const SECRET_MANAGE = 'system.secret.manage';

    /**
     * OPERATOR mendapat seluruh permission operasional.
     * ADMIN ditangani sebagai wildcard pada hasPermission().
     *
     * @var list<string>
     */
    private const OPERATOR_PERMISSIONS = [
        self::DASHBOARD,
        self::MASTER_DATA,
        self::MASTER_UJIAN,
        self::EXECUTION,
        self::RESULTS,
        self::LIVE_SCORING,
        self::BACKUP_CREATE,
        self::LOG_VIEW,
    ];

    public function hasPermission(string $role, string $permission): bool
    {
        $role = strtoupper(trim($role));

        if ($role === 'ADMIN') {
            return true;
        }

        if ($role !== 'OPERATOR') {
            return false;
        }

        return in_array($permission, self::OPERATOR_PERMISSIONS, true);
    }

    /**
     * Dipakai UI hanya untuk visibility/convenience.
     * Server tetap memeriksa permission melalui filter/service.
     *
     * @return list<string>
     */
    public function permissionsForRole(string $role): array
    {
        $role = strtoupper(trim($role));

        if ($role === 'ADMIN') {
            return [
                self::DASHBOARD,
                self::MASTER_DATA,
                self::MASTER_UJIAN,
                self::EXECUTION,
                self::RESULTS,
                self::LIVE_SCORING,
                self::BACKUP_CREATE,
                self::LOG_VIEW,
                self::USERS_MANAGE,
                self::SETTINGS_MANAGE,
                self::RESTORE,
                self::CLEAR_DATA,
                self::LOG_MANAGE,
                self::SECRET_MANAGE,
            ];
        }

        if ($role === 'OPERATOR') {
            return self::OPERATOR_PERMISSIONS;
        }

        return [];
    }
}
