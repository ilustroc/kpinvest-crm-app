<?php

namespace App\Imports;

use App\Services\Cartera\DataReconciliationService;

class DataImport
{
    public function __construct(
        protected ?DataReconciliationService $service = null
    ) {
        $this->service = $service ?? app(DataReconciliationService::class);
    }

    /**
     * Reconcile and import a cartera CSV file with 3-way reconciliation.
     *
     * @param string $filepath
     * @param string|null $expectedFingerprint
     * @return array [okCount, skipCount, errorsList, summaryReport]
     */
    public function execute(string $filepath, ?string $expectedFingerprint = null): array
    {
        return $this->service->execute($filepath, $expectedFingerprint);
    }

    /**
     * Generate diagnostic preview without writing changes to the database.
     *
     * @param string $filepath
     * @return array [totalProcessed, totalQuarantine, errorsList, summaryReport]
     */
    public function preview(string $filepath): array
    {
        return $this->service->preview($filepath);
    }
}
