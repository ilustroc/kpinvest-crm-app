<?php

namespace App\Support\Authorization;

use App\Models\User;

class Roles
{
    public const ADMINISTRADOR = 'administrador';
    public const SUPERVISOR = 'supervisor';
    public const ASESOR = 'asesor';
    public const SOPORTE = 'soporte';

    public const ADMIN_ROLES = [
        self::ADMINISTRADOR,
    ];

    public const ADMIN_USERS_ROLES = [
        self::ADMINISTRADOR,
        self::SUPERVISOR,
        self::SOPORTE,
    ];

    public const REPORT_ROLES = [
        self::ADMINISTRADOR,
        self::SUPERVISOR,
        self::SOPORTE,
    ];

    public const INTEGRATION_ROLES = [
        self::ADMINISTRADOR,
        self::SUPERVISOR,
        self::SOPORTE,
    ];

    public const WORKFLOW_REVIEW_ROLES = [
        self::ADMINISTRADOR,
        self::SUPERVISOR,
    ];

    public const FINAL_ROLES = [
        self::ADMINISTRADOR,
        self::SUPERVISOR,
        self::ASESOR,
        self::SOPORTE,
    ];

    public static function normalize(?string $role): string
    {
        return strtolower(trim((string) $role));
    }

    public static function has(User $user, array $roles): bool
    {
        return in_array(self::normalize($user->role), $roles, true);
    }

    public static function canAccessAdminUsers(User $user): bool
    {
        return self::has($user, self::ADMIN_USERS_ROLES);
    }

    public static function canAccessReports(User $user): bool
    {
        return self::has($user, self::REPORT_ROLES);
    }

    public static function canAccessIntegrations(User $user): bool
    {
        return self::has($user, self::INTEGRATION_ROLES);
    }

    public static function canReviewWorkflow(User $user): bool
    {
        return self::has($user, self::WORKFLOW_REVIEW_ROLES);
    }

    public static function canDeleteClientPayments(User $user): bool
    {
        return self::has($user, [
            self::ADMINISTRADOR,
            self::SISTEMAS,
            self::SUPERVISOR,
            self::SOPORTE,
        ]);
    }

    public static function creatableUserRoles(User $user): array
    {
        return match (self::normalize($user->role)) {
            self::ADMINISTRADOR => [
                self::ADMINISTRADOR,
                self::SUPERVISOR,
                self::ASESOR,
                self::SOPORTE,
            ],
            self::SUPERVISOR => [
                self::ASESOR,
                self::SOPORTE,
            ],
            default => [],
        };
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        $actorRole = self::normalize($actor->role);
        $targetRole = self::normalize($target->role);

        if (in_array($actorRole, self::ADMIN_ROLES, true)) {
            return true;
        }

        if ($actorRole === self::SUPERVISOR) {
            return in_array($targetRole, [self::ASESOR, self::SOPORTE], true)
                && (int) $target->supervisor_id === (int) $actor->id;
        }

        return false;
    }
}
