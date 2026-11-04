<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $protectedDatabases = [
            'kp_crm_revision_v3',
            'kp_crm_ui_preview',
            'production',
        ];

        if (in_array($database, $protectedDatabases, true)) {
            throw new \RuntimeException(
                "CRITICAL TEST ISOLATION FAILURE: Attempted to run tests against protected database [{$database}]. " .
                "Tests must only run against an isolated test database such as [kp_crm_testing] or in-memory sqlite."
            );
        }
    }

    public function assertDatabaseHas($table, array $data, $connection = null)
    {
        $data = $this->normalizeLegacyDocumentField($table, $data);
        return parent::assertDatabaseHas($table, $data, $connection);
    }

    public function assertDatabaseMissing($table, array $data, $connection = null)
    {
        $data = $this->normalizeLegacyDocumentField($table, $data);
        return parent::assertDatabaseMissing($table, $data, $connection);
    }

    protected function normalizeLegacyDocumentField($table, array $data): array
    {
        $docTables = ['promesas_pago', 'cna_solicitudes', 'pagos', 'cliente_bloqueos'];
        if (in_array($table, $docTables, true) && array_key_exists('dni', $data) && !array_key_exists('numdoc', $data)) {
            $data['numdoc'] = $data['dni'];
            unset($data['dni']);
        }
        return $data;
    }
}
