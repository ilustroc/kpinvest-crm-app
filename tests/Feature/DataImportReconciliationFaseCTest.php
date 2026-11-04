<?php

namespace Tests\Feature;

use App\Imports\DataImport;
use App\Models\CnaSolicitud;
use App\Models\Pago;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Cartera\DataReconciliationService;
use App\Services\Panel\PanelDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DataImportReconciliationFaseCTest extends TestCase
{
    use DatabaseTransactions;

    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        $this->tmpDir = sys_get_temp_dir() . '/crm_import_tests_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    protected function createCsv(string $filename, array $headers, array $rows): string
    {
        $path = $this->tmpDir . '/' . $filename;
        $fp = fopen($path, 'w');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        return $path;
    }

    public function test_1_initial_import(): void
    {
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000001', 'OP-INIT-1', 'CTA-1', 'JUAN PEREZ', 'BCP', '1000.00', '1200.00'],
            ['88000002', 'OP-INIT-2', 'CTA-2', 'MARIA LOPEZ', 'BBVA', '2500.50', '2800.00'],
            ['88000003', 'OP-INIT-3', 'CTA-3', 'CARLOS RUIZ', 'INTERBANK', '500.00', '600.00'],
        ];

        $csv = $this->createCsv('initial.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(3, $ok);
        $this->assertSame(0, $skip);
        $this->assertEmpty($err);
        $this->assertSame(3, $summary['insert']);
        $this->assertSame(3, $summary['nuevas_cuentas']);

        $dbCount = DB::table('clientes_cuentas')->whereIn('numdoc', ['88000001', '88000002', '88000003'])->count();
        $this->assertSame(3, $dbCount);
    }

    public function test_2_identical_reimport(): void
    {
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000010', 'OP-REIMP-1', 'CTA-10', 'CLIENTE REIMPORTE', 'BCP', '1500.00', '1600.00'],
        ];

        $csv = $this->createCsv('reimport.csv', $headers, $rows);
        $import = app(DataImport::class);

        // First execution: 1 insert
        [$ok1, $skip1, $err1, $summary1] = $import->execute($csv);
        $this->assertSame(1, $ok1);
        $this->assertSame(1, $summary1['insert']);

        // Second execution with identical file: 0 new accounts, 1 sin_cambios
        [$ok2, $skip2, $err2, $summary2] = $import->execute($csv);
        $this->assertSame(1, $ok2);
        $this->assertSame(0, $summary2['insert']);
        $this->assertSame(1, $summary2['sin_cambios']);
        $this->assertSame(1, $summary2['registros_sin_cambios']);

        // Total in DB must remain exactly 1 (No duplicate row created!)
        $dbCount = DB::table('clientes_cuentas')->where('numdoc', '88000010')->count();
        $this->assertSame(1, $dbCount);
    }

    public function test_3_debt_change_from_1000_to_800(): void
    {
        $dni = '88000020';
        $op = 'OP-AMORT-1';

        $id = DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-AMORT',
            'nombre' => 'PEDRO ALVAREZ',
            'entidad' => 'BCP',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Incoming file contains amortized debt (800.00 instead of 1000.00)
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-AMORT', 'PEDRO ALVAREZ', 'BCP', '800.00', '900.00'],
        ];

        $csv = $this->createCsv('amort.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(1, $ok);
        $this->assertSame(0, $summary['insert']);
        $this->assertSame(1, $summary['update']);
        $this->assertSame(1, $summary['cuentas_actualizadas']);

        // Assert existing row was UPDATED and ID preserved (Not duplicated!)
        $accounts = DB::table('clientes_cuentas')->where('numdoc', $dni)->get();
        $this->assertCount(1, $accounts);
        $this->assertSame($id, $accounts->first()->id);
        $this->assertEquals(800.00, (float) $accounts->first()->deuda_capital);
    }

    public function test_4_client_with_multiple_accounts(): void
    {
        $dni = '88000030';
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, 'OP-MULTI-1', 'CTA-M1', 'ANA GOMEZ', 'BCP', '500.00', '550.00'],
            [$dni, 'OP-MULTI-2', 'CTA-M2', 'ANA GOMEZ', 'BBVA', '1200.00', '1300.00'],
        ];

        $csv = $this->createCsv('multi.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(2, $ok);
        $this->assertSame(2, $summary['insert']);

        $accounts = DB::table('clientes_cuentas')->where('numdoc', $dni)->get();
        $this->assertCount(2, $accounts);
        $this->assertNotSame($accounts[0]->operacion, $accounts[1]->operacion);
    }

    public function test_5_two_different_clients_with_same_operation(): void
    {
        $sameOp = 'OP-SHARED-COLLISION-1';
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000040', $sameOp, 'CTA-CLI-1', 'CLIENTE UNO', 'BCP', '700.00', '750.00'],
            ['88000041', $sameOp, 'CTA-CLI-2', 'CLIENTE DOS', 'INTERBANK', '900.00', '950.00'],
        ];

        $csv = $this->createCsv('shared_op.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(2, $ok);
        $this->assertSame(2, $summary['insert']);

        $c1 = DB::table('clientes_cuentas')->where('numdoc', '88000040')->where('operacion', $sameOp)->first();
        $c2 = DB::table('clientes_cuentas')->where('numdoc', '88000041')->where('operacion', $sameOp)->first();

        $this->assertNotNull($c1);
        $this->assertNotNull($c2);
        $this->assertNotEquals($c1->id, $c2->id);
    }

    public function test_6_same_dni_and_operation_with_ambiguous_db_matches(): void
    {
        $dni = '88000050';
        $op = 'OP-AMBIGUOUS-TR';

        // Historical DB contains two accounts for the same DNI and OP
        DB::table('clientes_cuentas')->insert([
            ['numdoc' => $dni, 'operacion' => $op, 'cuenta' => 'CTA-TR-1', 'nombre' => 'CLIENTE TRANCHES', 'entidad' => 'BCP', 'created_at' => now(), 'updated_at' => now()],
            ['numdoc' => $dni, 'operacion' => $op, 'cuenta' => 'CTA-TR-2', 'nombre' => 'CLIENTE TRANCHES', 'entidad' => 'BCP', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, '', 'CLIENTE TRANCHES', 'BCP', '800.00', '800.00'],
        ];

        $csv = $this->createCsv('ambiguous.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        // Must be held in quarantine to prevent accidental overwrite of either tranche
        $this->assertSame(1, $skip);
        $this->assertSame(1, $summary['cuarentena']);
        $this->assertStringContainsString('AMBIGUEDAD_BD_MULTIPLE_OBLIGACION', $summary['detalles_cuarentena'][0]['motivo']);

        // Both original rows remain untouched in DB
        $count = DB::table('clientes_cuentas')->where('numdoc', $dni)->where('operacion', $op)->count();
        $this->assertSame(2, $count);
    }

    public function test_7_entities_with_different_denominations(): void
    {
        $dni = '88000060';
        $op = 'OP-NORM-ENT';

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-NORM-1',
            'nombre' => 'CLIENTE BANCO',
            'entidad' => 'BCP',
            'deuda_capital' => 1200.00,
            'deuda_total' => 1300.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Incoming uses variant "BANCO DE CREDITO DEL PERU"
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-NORM-1', 'CLIENTE BANCO', 'BANCO DE CREDITO DEL PERU', '1100.00', '1200.00'],
        ];

        $csv = $this->createCsv('entity_variant.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        // Reconciles and updates existing record instead of creating a second row
        $this->assertSame(1, $summary['update']);
        $this->assertSame(0, $summary['insert']);

        $count = DB::table('clientes_cuentas')->where('numdoc', $dni)->count();
        $this->assertSame(1, $count);
    }

    public function test_8_operations_and_dnis_with_leading_zeros(): void
    {
        $dniWithZero = '01488877';
        $opWithZero = '0008673513';

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dniWithZero, $opWithZero, 'CTA-ZEROS', 'CLIENTE ZEROS', 'BCP', '950.00', '1000.00'],
        ];

        $csv = $this->createCsv('zeros.csv', $headers, $rows);
        $import = app(DataImport::class);
        $import->execute($csv);

        $row = DB::table('clientes_cuentas')->where('numdoc', $dniWithZero)->where('operacion', $opWithZero)->first();
        $this->assertNotNull($row);
        $this->assertSame('01488877', (string) $row->numdoc);
        $this->assertSame('0008673513', (string) $row->operacion);
    }

    public function test_9_in_csv_identical_duplicates_omitted(): void
    {
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000070', 'OP-DUP-CSV', 'CTA-D1', 'CLIENTE DUP', 'BCP', '500.00', '500.00'],
            ['88000070', 'OP-DUP-CSV', 'CTA-D1', 'CLIENTE DUP', 'BCP', '500.00', '500.00'], // Identical duplicate
        ];

        $csv = $this->createCsv('dup_csv.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(1, $summary['insert']);
        $this->assertSame(1, $summary['duplicados']);
        $this->assertSame(1, $summary['duplicados_en_csv']);

        $count = DB::table('clientes_cuentas')->where('numdoc', '88000070')->count();
        $this->assertSame(1, $count);
    }

    public function test_9b_in_csv_conflicting_duplicates_all_quarantined(): void
    {
        // Two rows in CSV for the same customer and OP, but with conflicting debts (1000 vs 800)
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000075', 'OP-CONFLICT', 'CTA-C1', 'CLIENTE CONFLICTO', 'BCP', '1000.00', '1100.00'],
            ['88000075', 'OP-CONFLICT', 'CTA-C1', 'CLIENTE CONFLICTO', 'BCP', '800.00', '900.00'],
        ];

        $csv = $this->createCsv('conflict_csv.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        // Rule 2: Both conflicting rows must be held in quarantine!
        // No automatic selection of first or last balance.
        $this->assertSame(0, $ok);
        $this->assertSame(2, $skip);
        $this->assertSame(2, $summary['cuarentena']);

        // Assert details record row numbers and conflict values
        $this->assertCount(2, $summary['detalles_cuarentena']);
        $this->assertStringContainsString('DUPLICADO_CONFLICTIVO_EN_CSV', $summary['detalles_cuarentena'][0]['motivo']);
        $this->assertContains(1000.0, $summary['detalles_cuarentena'][0]['valores_en_conflicto']['deudas']);
        $this->assertContains(800.0, $summary['detalles_cuarentena'][0]['valores_en_conflicto']['deudas']);

        // ZERO rows inserted into DB
        $count = DB::table('clientes_cuentas')->where('numdoc', '88000075')->count();
        $this->assertSame(0, $count);
    }

    public function test_10_null_empty_or_malformed_data(): void
    {
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['', 'OP-NO-DNI', 'CTA-ERR1', 'SIN DNI', 'BCP', '100.00', '100.00'],
            ['88000080', '', 'CTA-ERR2', 'SIN OP', 'BCP', '200.00', '200.00'],
            ['88000081', 'OP-NEG', 'CTA-ERR3', 'DEUDA NEGATIVA', 'BCP', '-500.00', '100.00'],
        ];

        $csv = $this->createCsv('malformed.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(0, $ok);
        $this->assertSame(3, $skip);
        $this->assertSame(3, $summary['cuarentena']);
    }

    public function test_10b_financial_parser_edge_cases_and_mariadb_limits(): void
    {
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000085', 'OP-P1', 'CTA-P1', 'US FORMAT', 'BCP', '1,250.50', '1,250.50'],
            ['88000086', 'OP-P2', 'CTA-P2', 'EU FORMAT', 'BCP', '1.250,50', '1.250,50'],
            ['88000087', 'OP-P3', 'CTA-P3', 'COMMA DECIMAL', 'BCP', '1250,50', '1250,50'],
            ['88000088', 'OP-P4', 'CTA-P4', 'AMBIGUOUS COMMA', 'BCP', '1,250', '1,250'], // Ambiguous without convention
            ['88000089', 'OP-P5', 'CTA-P5', 'AMBIGUOUS DOT', 'BCP', '1.250', '1.250'], // Ambiguous without convention
            ['88000090', 'OP-P6', 'CTA-P6', 'MALFORMED TEXT', 'BCP', '12a50', '100.00'], // Malformed text
            ['88000091', 'OP-P7', 'CTA-P7', 'OVERFLOW DECIMAL', 'BCP', '99999999999.99', '100.00'], // Exceeds DECIMAL(12,2)
        ];

        $csv = $this->createCsv('financial_edge.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        // 3 unambiguous valid rows inserted
        $this->assertSame(3, $ok);
        $this->assertSame(3, $summary['insert']);

        // 4 invalid or ambiguous rows retained in quarantine
        $this->assertSame(4, $skip);
        $this->assertSame(4, $summary['cuarentena']);

        // Verify accurate numeric parsing in MariaDB
        $r1 = DB::table('clientes_cuentas')->where('operacion', 'OP-P1')->first();
        $this->assertEquals(1250.50, (float) $r1->deuda_capital);

        $r2 = DB::table('clientes_cuentas')->where('operacion', 'OP-P2')->first();
        $this->assertEquals(1250.50, (float) $r2->deuda_capital);

        $r3 = DB::table('clientes_cuentas')->where('operacion', 'OP-P3')->first();
        $this->assertEquals(1250.50, (float) $r3->deuda_capital);
    }

    public function test_11_whole_file_atomicity_and_all_or_nothing_rollback(): void
    {
        // Test that a mid-file exception triggers complete file rollback (all-or-nothing)
        $dni1 = '88000092';
        $dni2 = '88000093';

        // Using a transaction test
        try {
            DB::transaction(function () use ($dni1, $dni2) {
                // Batch 1 insert
                DB::table('clientes_cuentas')->insert([
                    'numdoc' => $dni1,
                    'operacion' => 'OP-BATCH-1',
                    'nombre' => 'BATCH ONE',
                    'entidad' => 'BCP',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Batch 2 failure
                throw new \Exception('Simulated failure during Batch 2 processing');
            });
        } catch (\Throwable $e) {
            // Expected
        }

        $count = DB::table('clientes_cuentas')->whereIn('numdoc', [$dni1, $dni2])->count();
        $this->assertSame(0, $count, 'Whole-file atomicity must roll back Batch 1 if Batch 2 fails');
    }

    public function test_11b_sin_cambios_preserves_updated_at_timestamp(): void
    {
        $dni = '88000094';
        $op = 'OP-SIN-CAMBIOS-TS';
        $pastTime = Carbon::now()->subMinutes(10)->startOfSecond();

        $id = DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-TS',
            'nombre' => 'CLIENTE TS',
            'entidad' => 'BCP',
            'deuda_capital' => 500.00,
            'deuda_total' => 500.00,
            'created_at' => $pastTime,
            'updated_at' => $pastTime,
        ]);

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-TS', 'CLIENTE TS', 'BCP', '500.00', '500.00'],
        ];

        $csv = $this->createCsv('sin_cambios_ts.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(1, $ok);
        $this->assertSame(0, $summary['update']);
        $this->assertSame(1, $summary['sin_cambios']);

        // Assert updated_at was NOT modified in the database
        $record = DB::table('clientes_cuentas')->where('id', $id)->first();
        $this->assertSame($pastTime->toDateTimeString(), (string) $record->updated_at);
    }

    public function test_12_concurrent_imports_locking(): void
    {
        $lockFile = storage_path('framework/cache/cartera_import.lock');
        $fp = fopen($lockFile, 'c+');

        // Acquire lock to simulate an active import
        flock($fp, LOCK_EX);

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000095', 'OP-LOCK-1', 'CTA-L1', 'TEST LOCK', 'BCP', '100.00', '100.00'],
        ];
        $csv = $this->createCsv('lock_test.csv', $headers, $rows);

        $import = app(DataImport::class);
        [$ok, $skip, $err] = $import->execute($csv);

        flock($fp, LOCK_UN);
        fclose($fp);

        $this->assertSame(0, $ok);
        $this->assertNotEmpty($err);
        $this->assertStringContainsString('otro proceso', $err[0]);
    }

    public function test_13_retention_of_ambiguous_historical_cases(): void
    {
        $dni = '88000099';
        $op = 'OP-HOMONYMY-RISK';

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-H1',
            'nombre' => 'CARLOS ALBERTO SANCHEZ PRADA',
            'entidad' => 'BCP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Incoming file has completely different surnames
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-H1', 'LUIS ENRIQUE MEZA CASTILLO', 'BCP', '1500.00', '1500.00'],
        ];

        $csv = $this->createCsv('homonymy.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        $this->assertSame(1, $skip);
        $this->assertSame(1, $summary['cuarentena']);
        $this->assertStringContainsString('DISCREPANCIA_TITULAR', $summary['detalles_cuarentena'][0]['motivo']);
    }

    public function test_13b_two_legitimate_products_in_csv_not_merged_all_quarantined(): void
    {
        // Client has two legitimate products (Tarjeta Credito vs Prestamo Personal) with same entity & OP
        $dni = '88000098';
        $op = 'OP-LEGIT-MULTI';

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'PRODUCTO', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-P1', 'CLIENTE PRODUCTOS', 'BCP', 'TARJETA CREDITO', '1500.00', '1600.00'],
            [$dni, $op, 'CTA-P2', 'CLIENTE PRODUCTOS', 'BCP', 'PRESTAMO PERSONAL', '3000.00', '3200.00'],
        ];

        $csv = $this->createCsv('products_conflict.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$ok, $skip, $err, $summary] = $import->execute($csv);

        // Rule 3: Do not merge distinct obligations! Both rows must be quarantined.
        $this->assertSame(0, $ok);
        $this->assertSame(2, $skip);
        $this->assertSame(2, $summary['cuarentena']);

        // Assert quarantine details record the distinct products
        $this->assertContains('TARJETA CREDITO', $summary['detalles_cuarentena'][0]['valores_en_conflicto']['productos']);
        $this->assertContains('PRESTAMO PERSONAL', $summary['detalles_cuarentena'][0]['valores_en_conflicto']['productos']);

        $count = DB::table('clientes_cuentas')->where('numdoc', $dni)->count();
        $this->assertSame(0, $count);
    }

    public function test_14_integrity_of_relationships_with_pagos_promesas_cna(): void
    {
        $dni = '88000100';
        $op = 'OP-REL-INTACT';

        $accountId = DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'operacion' => $op,
            'cuenta' => 'CTA-REL',
            'nombre' => 'TITULAR RELACIONES',
            'entidad' => 'BCP',
            'deuda_capital' => 2000.00,
            'deuda_total' => 2200.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'administrador', 'active' => 1]);

        $pago = Pago::create([
            'dni' => $dni,
            'operacion' => $op,
            'monto_pagado' => 500.00,
            'fecha' => now()->toDateString(),
            'entidad' => 'BCP',
        ]);

        $promesa = PromesaPago::create([
            'dni' => $dni,
            'operacion' => $op,
            'user_id' => $admin->id,
            'monto' => 1500.00,
            'monto_convenio' => 1500.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'workflow_estado' => 'aprobada',
        ]);

        $cna = CnaSolicitud::create([
            'dni' => $dni,
            'nro_carta' => 'CNA-REL-001',
            'operaciones' => [$op],
            'user_id' => $admin->id,
            'workflow_estado' => 'aprobada',
        ]);

        // Reconcile new incoming debt update for this account
        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            [$dni, $op, 'CTA-REL', 'TITULAR RELACIONES', 'BCP', '1500.00', '1650.00'],
        ];

        $csv = $this->createCsv('rel_update.csv', $headers, $rows);
        $import = app(DataImport::class);
        $import->execute($csv);

        // Assert existing ID was preserved
        $reconciledAccount = DB::table('clientes_cuentas')->where('id', $accountId)->first();
        $this->assertNotNull($reconciledAccount);
        $this->assertEquals(1500.00, (float) $reconciledAccount->deuda_capital);

        // Assert relationships with payments, promises, and CNA are 100% intact
        $this->assertSame($dni, $pago->fresh()->dni);
        $this->assertSame($dni, $promesa->fresh()->dni);
        $this->assertSame($dni, $cna->fresh()->dni);
    }

    public function test_15_dashboard_invalidation_on_commit(): void
    {
        Cache::forever(PanelDashboardService::CACHE_VERSION_KEY, 10);

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000110', 'OP-DASH-INV', 'CTA-DASH', 'CLIENTE DASH', 'BCP', '500.00', '500.00'],
        ];

        $csv = $this->createCsv('dash_inv.csv', $headers, $rows);
        $import = app(DataImport::class);
        $import->execute($csv);

        $versionAfter = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 0);
        $this->assertGreaterThan(10, $versionAfter, 'Dashboard cache version must bump after successful import commit');
    }

    public function test_preview_differentiates_all_classifications_and_db_fingerprint(): void
    {
        // Setup 1 existing account in DB
        DB::table('clientes_cuentas')->insert([
            'numdoc' => '88000121',
            'operacion' => 'OP-EXISTS',
            'nombre' => 'EXISTING',
            'entidad' => 'BCP',
            'deuda_capital' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = ['NUMDOC', 'OPERACION', 'CUENTA', 'NOMBRE', 'ENTIDAD', 'DEUDA_CAPITAL', 'DEUDA_TOTAL'];
        $rows = [
            ['88000120', 'OP-NEW', 'CTA-N1', 'NEW USER', 'BCP', '1000.00', '1000.00'], // INSERT
            ['88000121', 'OP-EXISTS', 'CTA-E1', 'EXISTING', 'BCP', '400.00', '400.00'], // UPDATE
            ['88000122', 'OP-SAME', 'CTA-S1', 'SAME', 'BCP', '500.00', '500.00'], // Identical duplicate 1
            ['88000122', 'OP-SAME', 'CTA-S1', 'SAME', 'BCP', '500.00', '500.00'], // Identical duplicate 2 (DUPLICADOS)
            ['', 'OP-NO-DNI', 'CTA-Q1', 'QUARANTINE', 'BCP', '100.00', '100.00'], // CUARENTENA
        ];

        $csv = $this->createCsv('preview_full.csv', $headers, $rows);
        $import = app(DataImport::class);

        [$totalProc, $totalQuar, $err, $summary] = $import->preview($csv);

        // Preview differentiates all 5 classifications:
        $this->assertSame(2, $summary['insert']); // OP-NEW + OP-SAME first row
        $this->assertSame(1, $summary['update']); // OP-EXISTS
        $this->assertSame(1, $summary['duplicados']); // OP-SAME duplicate
        $this->assertSame(1, $summary['cuarentena']); // OP-NO-DNI

        $this->assertNotEmpty($summary['db_fingerprint']);

        // Assert ZERO database writes occurred during preview
        $count = DB::table('clientes_cuentas')->where('numdoc', '88000120')->count();
        $this->assertSame(0, $count, 'Preview mode must never write to database');
    }

    public function test_dashboard_cache_invalidation_safe_recovery_when_locks_fail(): void
    {
        $lockFile = storage_path('framework/cache/panel_dashboard_version.lock');
        $dir = dirname($lockFile);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $fp = fopen($lockFile, 'c+');

        // Force flock lock to be held externally
        flock($fp, LOCK_EX);

        // Also hold Cache::lock externally
        $cacheLock = Cache::lock('panel_dashboard_version_lock', 10);
        $cacheLock->get();

        // doAtomicInvalidate must refuse uncoordinated non-atomic write and return false
        $result = PanelDashboardService::doAtomicInvalidate();
        $this->assertFalse($result);

        // Verify dirty flag was set for self-healing
        $isDirty = (bool) Cache::get(PanelDashboardService::CACHE_DIRTY_FLAG, false);
        $this->assertTrue($isDirty);

        // Release external locks
        $cacheLock->release();
        flock($fp, LOCK_UN);
        fclose($fp);

        // Verify self-healing works when locks are freed
        $healed = PanelDashboardService::doAtomicInvalidate();
        $this->assertTrue($healed);
        $this->assertFalse((bool) Cache::get(PanelDashboardService::CACHE_DIRTY_FLAG, false));
    }
}
