<?php

namespace App\Services\Ccd;

use Illuminate\Database\ConnectionInterface;

class CcdAqpAssociationApplyGuard
{
    public function rejectionReason(ConnectionInterface $connection): ?string
    {
        if ($connection->getDatabaseName() !== CcdAutoAssociationApplyGuard::STAGING_DATABASE) {
            return 'La aplicación AQP sólo se permite en la base de staging '.CcdAutoAssociationApplyGuard::STAGING_DATABASE.'.';
        }

        if (filter_var(config('ccd.auto_association_apply_enabled', false), FILTER_VALIDATE_BOOL) !== true) {
            return 'La aplicación AQP requiere CCD_AUTO_ASSOCIATION_APPLY_ENABLED=true.';
        }

        if (filter_var(config('ccd.aqp_association_apply_enabled', false), FILTER_VALIDATE_BOOL) !== true) {
            return 'La aplicación AQP requiere CCD_AQP_ASSOCIATION_APPLY_ENABLED=true.';
        }

        if (filter_var(config('ccd.aqp_human_review_approved', false), FILTER_VALIDATE_BOOL) !== true) {
            return 'La aplicación AQP requiere CCD_AQP_HUMAN_REVIEW_APPROVED=true.';
        }

        return null;
    }
}
