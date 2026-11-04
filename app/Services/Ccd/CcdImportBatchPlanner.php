<?php

namespace App\Services\Ccd;

class CcdImportBatchPlanner
{
    private const PENDING_STATUSES = [
        'would_import',
        'would_reuse',
        'would_repair',
    ];

    private const CRITICAL_STATUSES = [
        'conflict',
        'error',
        'local_without_database',
    ];

    public function plan(array $inspections, int $batchSize = 0): array
    {
        $seenHashes = [];
        $duplicateHashes = 0;
        $counts = [
            'already_imported' => 0,
            'would_import' => 0,
            'would_reuse' => 0,
            'would_repair' => 0,
            'conflict' => 0,
            'error' => 0,
            'local_without_database' => 0,
        ];
        $pending = [];
        $normalized = [];

        foreach ($inspections as $inspection) {
            $status = $inspection['status'];
            $sha256 = $inspection['sha256'] ?? null;

            if ($sha256 !== null && isset($seenHashes[$sha256])) {
                $duplicateHashes++;

                if ($status === 'would_import') {
                    $status = 'would_reuse';
                }
            } elseif ($sha256 !== null) {
                $seenHashes[$sha256] = true;
            }

            $inspection['status'] = $status;
            $normalized[] = $inspection;
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if (in_array($status, self::PENDING_STATUSES, true)) {
                $pending[] = $inspection;
            }
        }

        $selected = $batchSize > 0
            ? array_slice($pending, 0, $batchSize)
            : $pending;

        $critical = 0;

        foreach (self::CRITICAL_STATUSES as $status) {
            $critical += $counts[$status];
        }

        return [
            'items' => $normalized,
            'selected' => $selected,
            'counts' => $counts,
            'pending' => count($pending),
            'duplicate_hashes' => $duplicateHashes,
            'critical' => $critical,
        ];
    }
}
