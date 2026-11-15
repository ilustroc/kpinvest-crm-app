<?php

namespace App\Services\Ccd;

use Illuminate\Database\ConnectionInterface;

class CcdAutoAssociationApplyGuard
{
    public const STAGING_DATABASE = 'u480021566_prueba';

    public function rejectionReason(ConnectionInterface $connection): ?string
    {
        if ($connection->getDatabaseName() !== self::STAGING_DATABASE) {
            return 'El modo --apply sólo se permite en la base de staging '.self::STAGING_DATABASE.'.';
        }

        if (filter_var(config('ccd.auto_association_apply_enabled', false), FILTER_VALIDATE_BOOL) !== true) {
            return 'El modo --apply requiere CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true.';
        }

        return null;
    }
}
