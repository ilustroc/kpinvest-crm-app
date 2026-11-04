<?php

namespace Tests\Feature;

use App\Imports\AsignacionImport;
use App\Exports\AssignmentExport;
use App\Models\AsignarCliente;
use App\Models\ClienteCuenta;
use App\Models\User;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\AssignmentReportService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ClienteAssignmentModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1 & 2. Esquema transformado de asignar_clientes y conservación de registros históricos.
     */
    public function test_schema_and_historical_records_are_preserved(): void
    {
        $this->assertTrue(Schema::hasTable('asignar_clientes'));

        $expectedColumns = [
            'id',
            'numdoc',
            'operacion',
            'cliente_cuenta_id',
            'name',
            'solicitante_user_id',
            'asesor_destino_user_id',
            'fecha_solicitud',
            'motivo',
            'estado',
            'aprobado_por_user_id',
            'fecha_aprobacion',
            'observacion_resolucion',
            'origen',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                Schema::hasColumn('asignar_clientes', $col),
                "Falta la columna {$col} en asignar_clientes."
            );
        }

        // Verify UNIQUE(numdoc, operacion) was removed so linear history is allowed
        $uniqueOld = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'asignar_clientes')
            ->where('index_name', 'unique_asignacion_cliente')
            ->exists();
        $this->assertFalse($uniqueOld, 'El índice único antiguo unique_asignacion_cliente debe haber sido eliminado.');

        // Verify historical records without mapped users do not invent solicitantes/aprobadores
        $knownUser = $this->makeUser('asesor', ['name' => 'ASESOR HISTORICO MAPEABLE']);

        DB::table('asignar_clientes')->insert([
            [
                'numdoc' => '00900001',
                'operacion' => 'OP-HIST-BOLSA',
                'name' => 'BOLSA',
            ],
            [
                'numdoc' => '00900002',
                'operacion' => 'OP-HIST-USER',
                'name' => 'ASESOR HISTORICO MAPEABLE',
            ],
        ]);

        // Run the same unambiguous backfill rule used in the migration
        DB::statement("
            UPDATE `asignar_clientes` ac
            JOIN (
                SELECT MIN(t.id) AS user_id, t.norm_name
                FROM (
                    SELECT id, TRIM(UPPER(REPLACE(name, UNHEX('C2A0'), ' '))) AS norm_name
                    FROM `users`
                ) t
                GROUP BY t.norm_name
                HAVING COUNT(*) = 1
            ) u ON TRIM(UPPER(REPLACE(ac.name, UNHEX('C2A0'), ' '))) = u.norm_name
            SET ac.asesor_destino_user_id = u.user_id
            WHERE ac.asesor_destino_user_id IS NULL AND ac.name IS NOT NULL AND ac.name != ''
        ");

        $bolsaSample = DB::table('asignar_clientes')
            ->where('numdoc', '00900001')
            ->where('operacion', 'OP-HIST-BOLSA')
            ->first();

        $this->assertNotNull($bolsaSample);
        $this->assertNull($bolsaSample->solicitante_user_id);
        $this->assertNull($bolsaSample->asesor_destino_user_id);
        $this->assertNull($bolsaSample->aprobado_por_user_id);
        $this->assertSame('aprobada', $bolsaSample->estado);

        $mappedSample = DB::table('asignar_clientes')
            ->where('numdoc', '00900002')
            ->where('operacion', 'OP-HIST-USER')
            ->first();

        $this->assertNotNull($mappedSample);
        $this->assertNull($mappedSample->solicitante_user_id);
        $this->assertSame((int) $knownUser->id, (int) $mappedSample->asesor_destino_user_id);
        $this->assertNull($mappedSample->aprobado_por_user_id);
        $this->assertSame('aprobada', $mappedSample->estado);
    }

    /**
     * 3 & 20. Solicitud manual de asignación desde Ficha 360° preservando ceros iniciales.
     */
    public function test_manual_assignment_request_from_ficha_360_preserves_leading_zeros(): void
    {
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor Solicitud Manual']);
        $numdoc = '00458912';
        $operacion = '000987654';

        $cuenta = $this->createAccount($numdoc, $operacion, 'CLIENTE CEROS INICIALES');

        $response = $this->actingAs($asesor)
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => [$operacion],
                'motivo' => 'Cliente contactado telefónicamente solicita negociación.',
            ]);

        $response->assertRedirect(route('clientes.show', $numdoc));
        $response->assertSessionHasNoErrors();

        $record = AsignarCliente::query()
            ->where('numdoc', $numdoc)
            ->where('operacion', $operacion)
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('00458912', $record->numdoc);
        $this->assertSame('000987654', $record->operacion);
        $this->assertSame((int) $cuenta->id, $record->cliente_cuenta_id);
        $this->assertSame((int) $asesor->id, $record->solicitante_user_id);
        $this->assertSame((int) $asesor->id, $record->asesor_destino_user_id);
        $this->assertNotNull($record->fecha_solicitud);
        $this->assertSame('Cliente contactado telefónicamente solicita negociación.', $record->motivo);
        $this->assertSame(AsignarCliente::ESTADO_PENDIENTE, $record->estado);
        $this->assertNull($record->aprobado_por_user_id);
        $this->assertNull($record->fecha_aprobacion);
        $this->assertSame(AsignarCliente::ORIGEN_MANUAL, $record->origen);
    }

    /**
     * 4. Solicitud manual con múltiples operaciones seleccionadas crea un registro por operación.
     */
    public function test_manual_request_with_multiple_operations_creates_granular_records(): void
    {
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor Multi Op']);
        $numdoc = '00789123';

        $c1 = $this->createAccount($numdoc, '00111', 'CLIENTE MULTI OP');
        $c2 = $this->createAccount($numdoc, '00222', 'CLIENTE MULTI OP');
        $c3 = $this->createAccount($numdoc, '00333', 'CLIENTE MULTI OP');

        $this->actingAs($asesor)
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => ['00111', '00222', '00333'],
                'motivo' => 'Gestión integral de las 3 cuentas del titular.',
            ])
            ->assertRedirect(route('clientes.show', $numdoc))
            ->assertSessionHasNoErrors();

        $records = AsignarCliente::query()
            ->where('numdoc', $numdoc)
            ->where('solicitante_user_id', $asesor->id)
            ->orderBy('operacion')
            ->get();

        $this->assertCount(3, $records);
        $this->assertSame(['00111', '00222', '00333'], $records->pluck('operacion')->all());
        $this->assertSame([(int) $c1->id, (int) $c2->id, (int) $c3->id], $records->pluck('cliente_cuenta_id')->all());
    }

    /**
     * 5. Solicitud manual rechazada cuando falta el motivo.
     */
    public function test_manual_request_is_rejected_when_motivo_is_missing(): void
    {
        $asesor = $this->makeUser('asesor');
        $numdoc = '00654321';
        $this->createAccount($numdoc, 'OP-SIN-MOTIVO');

        $this->actingAs($asesor)
            ->from(route('clientes.show', $numdoc))
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => ['OP-SIN-MOTIVO'],
                'motivo' => '   ',
            ])
            ->assertRedirect(route('clientes.show', $numdoc))
            ->assertSessionHasErrors('motivo');

        $this->assertDatabaseMissing('asignar_clientes', [
            'numdoc' => $numdoc,
            'operacion' => 'OP-SIN-MOTIVO',
        ]);
    }

    /**
     * 6. Prevención de solicitudes pendientes duplicadas para la misma cuenta y asesor.
     */
    public function test_prevents_duplicate_pending_requests_for_same_account_and_advisor(): void
    {
        $asesor = $this->makeUser('asesor');
        $numdoc = '00998877';
        $this->createAccount($numdoc, 'OP-DUP-PEND');

        $this->actingAs($asesor)
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => ['OP-DUP-PEND'],
                'motivo' => 'Primera solicitud válida.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($asesor)
            ->from(route('clientes.show', $numdoc))
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => ['OP-DUP-PEND'],
                'motivo' => 'Intento duplicado mientras sigue pendiente.',
            ])
            ->assertRedirect(route('clientes.show', $numdoc))
            ->assertSessionHasErrors('operaciones');

        $count = AsignarCliente::query()
            ->where('numdoc', $numdoc)
            ->where('operacion', 'OP-DUP-PEND')
            ->where('estado', AsignarCliente::ESTADO_PENDIENTE)
            ->count();

        $this->assertSame(1, $count);
    }

    /**
     * 7, 8 & 9. Aprobación por Administrador, Supervisor y Soporte.
     */
    public function test_approval_by_administrador_supervisor_and_soporte(): void
    {
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor Destino Flujo']);

        foreach (['administrador', 'supervisor', 'soporte'] as $idx => $role) {
            $reviewer = $this->makeUser($role, ['name' => 'Revisor ' . ucfirst($role)]);
            $numdoc = '0055000' . $idx;
            $operacion = '0077000' . $idx;
            $cuenta = $this->createAccount($numdoc, $operacion);

            $solicitud = AsignarCliente::query()->create([
                'numdoc' => $numdoc,
                'operacion' => $operacion,
                'cliente_cuenta_id' => $cuenta->id,
                'name' => $asesor->name,
                'solicitante_user_id' => $asesor->id,
                'asesor_destino_user_id' => $asesor->id,
                'fecha_solicitud' => Carbon::now('America/Lima')->subMinutes(10),
                'motivo' => "Solicitud para aprobar por {$role}",
                'estado' => AsignarCliente::ESTADO_PENDIENTE,
                'origen' => AsignarCliente::ORIGEN_MANUAL,
            ]);

            $this->actingAs($reviewer)
                ->from(route('autorizacion'))
                ->post(route('autorizacion.asignaciones.aprobar', $solicitud), [
                    'nota_estado' => "Aprobado conforme por {$role}",
                ])
                ->assertRedirect(route('autorizacion'))
                ->assertSessionHasNoErrors();

            $solicitud->refresh();
            $this->assertSame(AsignarCliente::ESTADO_APROBADA, $solicitud->estado);
            $this->assertSame((int) $reviewer->id, $solicitud->aprobado_por_user_id);
            $this->assertNotNull($solicitud->fecha_aprobacion);
            $this->assertSame("Aprobado conforme por {$role}", $solicitud->observacion_resolucion);
        }
    }

    /**
     * 10. Rechazo por Administrador, Supervisor y Soporte con motivo obligatorio.
     */
    public function test_rejection_by_administrador_supervisor_and_soporte_requires_reason(): void
    {
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor Rechazo']);

        foreach (['administrador', 'supervisor', 'soporte'] as $idx => $role) {
            $reviewer = $this->makeUser($role, ['name' => 'Rechazador ' . ucfirst($role)]);
            $numdoc = '0066000' . $idx;
            $operacion = '0088000' . $idx;
            $cuenta = $this->createAccount($numdoc, $operacion);

            $solicitud = AsignarCliente::query()->create([
                'numdoc' => $numdoc,
                'operacion' => $operacion,
                'cliente_cuenta_id' => $cuenta->id,
                'name' => $asesor->name,
                'solicitante_user_id' => $asesor->id,
                'asesor_destino_user_id' => $asesor->id,
                'fecha_solicitud' => Carbon::now('America/Lima')->subMinutes(5),
                'motivo' => 'Solicitud para prueba de rechazo',
                'estado' => AsignarCliente::ESTADO_PENDIENTE,
                'origen' => AsignarCliente::ORIGEN_MANUAL,
            ]);

            // 1. Intento sin motivo debe fallar con error de validación
            $this->actingAs($reviewer)
                ->from(route('autorizacion'))
                ->post(route('autorizacion.asignaciones.rechazar', $solicitud), [
                    'nota_estado' => '   ',
                ])
                ->assertRedirect(route('autorizacion'))
                ->assertSessionHasErrors('motivo_rechazo');

            $this->assertSame(AsignarCliente::ESTADO_PENDIENTE, $solicitud->fresh()->estado);

            // 2. Rechazo con motivo válido
            $this->actingAs($reviewer)
                ->from(route('autorizacion'))
                ->post(route('autorizacion.asignaciones.rechazar', $solicitud), [
                    'nota_estado' => "Cuenta pertenece a otra cartera ({$role})",
                ])
                ->assertRedirect(route('autorizacion'))
                ->assertSessionHasNoErrors();

            $solicitud->refresh();
            $this->assertSame(AsignarCliente::ESTADO_RECHAZADA, $solicitud->estado);
            $this->assertSame((int) $reviewer->id, $solicitud->aprobado_por_user_id);
            $this->assertNotNull($solicitud->fecha_aprobacion);
            $this->assertSame("Cuenta pertenece a otra cartera ({$role})", $solicitud->observacion_resolucion);
        }
    }

    /**
     * 11 & 12. Intento de aprobar/rechazar por Asesor (403) e intento de aprobar dos veces la misma solicitud.
     */
    public function test_asesor_cannot_approve_or_reject_and_double_approval_is_blocked(): void
    {
        $asesor = $this->makeUser('asesor');
        $admin = $this->makeUser('administrador');
        $numdoc = '00334455';
        $operacion = 'OP-SEC-01';
        $cuenta = $this->createAccount($numdoc, $operacion);

        $solicitud = AsignarCliente::query()->create([
            'numdoc' => $numdoc,
            'operacion' => $operacion,
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesor->name,
            'solicitante_user_id' => $asesor->id,
            'asesor_destino_user_id' => $asesor->id,
            'fecha_solicitud' => Carbon::now('America/Lima'),
            'motivo' => 'Solicitud seguridad',
            'estado' => AsignarCliente::ESTADO_PENDIENTE,
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        // Asesor recibe 403 al intentar aprobar o rechazar
        $this->actingAs($asesor)
            ->post(route('autorizacion.asignaciones.aprobar', $solicitud))
            ->assertForbidden();

        $this->actingAs($asesor)
            ->post(route('autorizacion.asignaciones.rechazar', $solicitud), ['nota_estado' => 'Intento asesor'])
            ->assertForbidden();

        // Admin aprueba por primera vez -> OK
        $this->actingAs($admin)
            ->post(route('autorizacion.asignaciones.aprobar', $solicitud))
            ->assertSessionHasNoErrors();

        // Intento de aprobar por segunda vez -> Bloqueado con error de validación
        $this->actingAs($admin)
            ->from(route('autorizacion'))
            ->post(route('autorizacion.asignaciones.aprobar', $solicitud))
            ->assertRedirect(route('autorizacion'))
            ->assertSessionHasErrors('asignacion');
    }

    /**
     * 13, 14 & 15. Regla de asignación vigente:
     * - Múltiples asignaciones históricas (mayor fecha_solicitud, desempate por mayor id).
     * - Solicitud antigua aprobada después de una asignación más reciente NO reemplaza la más reciente.
     * - Solicitud pendiente o rechazada NO altera el asesor actual.
     */
    public function test_vigente_assignment_rule_handles_chronology_out_of_order_approval_and_pending_rejected(): void
    {
        $admin = $this->makeUser('administrador', ['name' => 'Admin Aprobador']);
        $asesorAntiguo = $this->makeUser('asesor', ['name' => 'Asesor Solicitud 09AM']);
        $asesorReciente = $this->makeUser('asesor', ['name' => 'Asesor Solicitud 10AM']);
        $asesorPendiente = $this->makeUser('asesor', ['name' => 'Asesor Solicitud 11AM Pendiente']);
        $asesorRechazado = $this->makeUser('asesor', ['name' => 'Asesor Solicitud 12PM Rechazado']);

        $numdoc = '00112233';
        $operacion = '00991122';
        $cuenta = $this->createAccount($numdoc, $operacion);

        $service = app(ClienteAssignmentService::class);

        // 1. Solicitud A creada a las 09:00 AM (inicialmente pendiente)
        $solicitud09am = AsignarCliente::query()->create([
            'numdoc' => $numdoc,
            'operacion' => $operacion,
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesorAntiguo->name,
            'solicitante_user_id' => $asesorAntiguo->id,
            'asesor_destino_user_id' => $asesorAntiguo->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 09:00:00', 'America/Lima'),
            'motivo' => 'Solicitud 09:00 AM',
            'estado' => AsignarCliente::ESTADO_PENDIENTE,
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        // Mientras está pendiente, no hay asesor vigente
        $this->assertNull($service->resolveActiveForAccount($numdoc, $operacion, $cuenta->id));

        // 2. Asignación B creada y aprobada a las 10:00 AM
        $asignacion10am = AsignarCliente::query()->create([
            'numdoc' => $numdoc,
            'operacion' => $operacion,
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesorReciente->name,
            'solicitante_user_id' => $admin->id,
            'asesor_destino_user_id' => $asesorReciente->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 10:00:00', 'America/Lima'),
            'motivo' => 'carga masiva',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::parse('2026-11-04 10:00:00', 'America/Lima'),
            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
        ]);

        $vigente = $service->resolveActiveForAccount($numdoc, $operacion, $cuenta->id);
        $this->assertNotNull($vigente);
        $this->assertSame((int) $asignacion10am->id, (int) $vigente->id);
        $this->assertSame('Asesor Solicitud 10AM', $vigente->asesor_nombre);

        // 3. A las 10:30 AM se aprueba tardíamente la Solicitud A (cuya fecha_solicitud es 09:00 AM)
        $service->approve($admin, $solicitud09am, 'Aprobada a las 10:30 AM');

        // La asignación vigente DEBE seguir siendo la de las 10:00 AM porque fecha_solicitud (10:00) > (09:00)
        $vigenteAfterLateApproval = $service->resolveActiveForAccount($numdoc, $operacion, $cuenta->id);
        $this->assertSame((int) $asignacion10am->id, (int) $vigenteAfterLateApproval->id);
        $this->assertSame('Asesor Solicitud 10AM', $vigenteAfterLateApproval->asesor_nombre);

        // 4. Solicitudes posteriores en estado pendiente (11:00 AM) y rechazada (12:00 PM) NO alteran el asesor vigente
        AsignarCliente::query()->create([
            'numdoc' => $numdoc,
            'operacion' => $operacion,
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesorPendiente->name,
            'solicitante_user_id' => $asesorPendiente->id,
            'asesor_destino_user_id' => $asesorPendiente->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 11:00:00', 'America/Lima'),
            'motivo' => 'Solicitud pendiente 11AM',
            'estado' => AsignarCliente::ESTADO_PENDIENTE,
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        AsignarCliente::query()->create([
            'numdoc' => $numdoc,
            'operacion' => $operacion,
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesorRechazado->name,
            'solicitante_user_id' => $asesorRechazado->id,
            'asesor_destino_user_id' => $asesorRechazado->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 12:00:00', 'America/Lima'),
            'motivo' => 'Solicitud rechazada 12PM',
            'estado' => AsignarCliente::ESTADO_RECHAZADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::parse('2026-11-04 12:05:00', 'America/Lima'),
            'observacion_resolucion' => 'No procede cambio',
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        $vigenteFinal = $service->resolveActiveForAccount($numdoc, $operacion, $cuenta->id);
        $this->assertSame((int) $asignacion10am->id, (int) $vigenteFinal->id);
        $this->assertSame('Asesor Solicitud 10AM', $vigenteFinal->asesor_nombre);

        // Verificar que Ficha 360° muestra ese mismo asesor vigente
        $this->actingAs($admin)
            ->get(route('clientes.show', $numdoc))
            ->assertOk()
            ->assertSee('Asesor Solicitud 10AM');
    }

    /**
     * 16, 17, 18 & 19. Carga masiva desde Subir Asignación:
     * - Filas válidas crean registro aprobado con motivo 'carga masiva'.
     * - Reimportación idempotente contabiliza 'sin_cambio_efectivo' sin duplicar historial.
     * - Filas con cuenta inexistente o ambigua son rechazadas y reportadas.
     * - Filas con asesor inexistente o ambiguo son rechazadas y reportadas.
     */
    public function test_bulk_import_validation_idempotency_and_error_reporting(): void
    {
        $admin = $this->makeUser('administrador', ['name' => 'Admin Importador']);
        $asesorValido = $this->makeUser('asesor', [
            'name' => 'Asesor Unico Masivo',
            'email' => 'unico.masivo@kpinvest.pe',
        ]);

        // Crear dos usuarios con el mismo nombre para probar detección de asesor ambiguo
        $this->makeUser('asesor', ['name' => 'Asesor Duplicado', 'email' => 'dup1@kpinvest.pe']);
        $this->makeUser('asesor', ['name' => 'Asesor Duplicado', 'email' => 'dup2@kpinvest.pe']);

        // Cuenta válida con ceros iniciales
        $cuentaValida = $this->createAccount('00887766', '000554433', 'CLIENTE MASIVO OK');

        // Cuenta ambigua (dos registros con mismo numdoc + operacion en clientes_cuentas)
        $this->createAccount('00887799', 'OP-AMBIGUA', 'CLIENTE AMBIGUO 1', 'BCP');
        $this->createAccount('00887799', 'OP-AMBIGUA', 'CLIENTE AMBIGUO 2', 'BBVA');

        $csvContent = implode("\n", [
            'NUMDOC,OPERACION,NAME',
            '00887766,000554433,Asesor Unico Masivo',
            '00887766,000554433,Asesor Unico Masivo', // Duplicado dentro del CSV
            '99999999,OP-NO-EXISTE,Asesor Unico Masivo', // Cuenta inexistente
            '00887799,OP-AMBIGUA,Asesor Unico Masivo', // Cuenta ambigua
            '00887766,000554433,Asesor Inexistente XYZ', // (ya atrapado por duplicado CSV si mismo par, probemos otra cuenta para asesor inválido)
        ]);

        $cuentaParaAsesorInvalido = $this->createAccount('00887701', 'OP-ASESOR-INV', 'CLIENTE ASESOR INV');
        $cuentaParaAsesorAmbiguo = $this->createAccount('00887702', 'OP-ASESOR-AMB', 'CLIENTE ASESOR AMB');

        $csvContent = implode("\n", [
            'NUMDOC,OPERACION,NAME',
            '00887766,000554433,Asesor Unico Masivo',
            '00887766,000554433,Asesor Unico Masivo',
            '99999999,OP-NO-EXISTE,Asesor Unico Masivo',
            '00887799,OP-AMBIGUA,Asesor Unico Masivo',
            '00887701,OP-ASESOR-INV,Usuario Que No Existe',
            '00887702,OP-ASESOR-AMB,Asesor Duplicado',
        ]);

        // 1. Vista previa diagnóstica (no escribe en BD)
        $this->actingAs($admin)
            ->post(route('integracion.asignacion.preview'), [
                'archivo' => UploadedFile::fake()->createWithContent('preview_asignacion.csv', $csvContent),
            ])
            ->assertRedirect()
            ->assertSessionHas('asignacion_import_preview');

        $preview = session('asignacion_import_preview');
        $this->assertSame(1, $preview['nuevas_asignaciones']);
        $this->assertSame(0, $preview['sin_cambio_efectivo']);
        $this->assertSame(1, $preview['duplicados']);
        $this->assertSame(1, $preview['cuenta_inexistente']);
        $this->assertSame(1, $preview['cuenta_ambigua']);
        $this->assertSame(2, $preview['asesor_invalido']);

        // Confirmar que preview NO insertó en BD
        $this->assertDatabaseMissing('asignar_clientes', [
            'numdoc' => '00887766',
            'operacion' => '000554433',
        ]);

        // 2. Descarga del reporte de filas rechazadas
        $errResp = $this->actingAs($admin)->get(route('integracion.asignacion.errors'));
        $errResp->assertOk();
        $this->assertStringContainsString('reporte_observaciones_asignacion.csv', (string) $errResp->headers->get('content-disposition'));

        // 3. Ejecución real de carga masiva
        $this->actingAs($admin)
            ->post(route('integracion.asignacion.import'), [
                'archivo' => UploadedFile::fake()->createWithContent('import_asignacion.csv', $csvContent),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = AsignarCliente::query()
            ->where('numdoc', '00887766')
            ->where('operacion', '000554433')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame((int) $cuentaValida->id, $created->cliente_cuenta_id);
        $this->assertSame((int) $admin->id, $created->solicitante_user_id);
        $this->assertSame((int) $asesorValido->id, $created->asesor_destino_user_id);
        $this->assertSame((int) $admin->id, $created->aprobado_por_user_id);
        $this->assertSame('carga masiva', $created->motivo);
        $this->assertSame(AsignarCliente::ESTADO_APROBADA, $created->estado);
        $this->assertSame(AsignarCliente::ORIGEN_CARGA_MASIVA, $created->origen);
        $this->assertNotNull($created->fecha_solicitud);
        $this->assertNotNull($created->fecha_aprobacion);

        $template = $this->actingAs($admin)
            ->get(route('integracion.asignacion.template'));
        $template->assertOk();
        $this->assertStringContainsString('NUMDOC,OPERACION,NAME', $template->streamedContent());

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones', ['numdoc' => '00887766', 'operacion' => '000554433']))
            ->assertOk()
            ->assertSee('CLIENTE MASIVO OK')
            ->assertSee('Carga masiva')
            ->assertSee('Admin Importador');

        // 4. Reimportación idempotente del mismo archivo -> sin_cambio_efectivo = 1, nuevas_asignaciones = 0
        $this->actingAs($admin)
            ->post(route('integracion.asignacion.import'), [
                'archivo' => UploadedFile::fake()->createWithContent('reimport_asignacion.csv', $csvContent),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $resultSecondRun = session('asignacion_import_result');
        $this->assertSame(0, $resultSecondRun['nuevas_asignaciones']);
        $this->assertSame(1, $resultSecondRun['sin_cambio_efectivo']);

        $totalRowsForAccount = AsignarCliente::query()
            ->where('numdoc', '00887766')
            ->where('operacion', '000554433')
            ->count();
        $this->assertSame(1, $totalRowsForAccount, 'No debe duplicar el historial cuando el asesor vigente ya es el mismo.');
    }

    public function test_assignment_report_handles_historical_nulls_filters_pagination_and_excel(): void
    {
        $admin = $this->makeUser('administrador', ['name' => 'Admin Reporte']);
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor Reporte']);

        $historical = AsignarCliente::query()->create([
            'numdoc' => '0000001234',
            'operacion' => '0000005678',
            'name' => 'BOLSA',
            'fecha_solicitud' => null,
            'motivo' => null,
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'fecha_aprobacion' => null,
            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
        ]);

        $manualAccount = $this->createAccount('0000004321', '0000008765', 'CLIENTE MANUAL REPORTE');
        AsignarCliente::query()->create([
            'numdoc' => $manualAccount->numdoc,
            'operacion' => $manualAccount->operacion,
            'cliente_cuenta_id' => $manualAccount->id,
            'name' => $asesor->name,
            'solicitante_user_id' => $asesor->id,
            'asesor_destino_user_id' => $asesor->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 14:00:00', 'America/Lima'),
            'motivo' => 'Solicitud manual de reporte',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::parse('2026-11-04 14:05:00', 'America/Lima'),
            'origen' => AsignarCliente::ORIGEN_MANUAL,
        ]);

        $otherAccount = $this->createAccount('0000007777', '0000008765', 'CLIENTE MISMA OPERACIÓN');
        AsignarCliente::query()->create([
            'numdoc' => $otherAccount->numdoc,
            'operacion' => $otherAccount->operacion,
            'cliente_cuenta_id' => $otherAccount->id,
            'name' => $asesor->name,
            'solicitante_user_id' => $admin->id,
            'asesor_destino_user_id' => $asesor->id,
            'fecha_solicitud' => Carbon::parse('2026-11-04 13:00:00', 'America/Lima'),
            'motivo' => 'Carga distinta',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::parse('2026-11-04 13:00:00', 'America/Lima'),
            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
        ]);

        for ($i = 1; $i <= 26; $i++) {
            AsignarCliente::query()->create([
                'numdoc' => '0000009999',
                'operacion' => 'PAGE-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'name' => 'ESCALL',
                'fecha_solicitud' => Carbon::parse('2026-10-01 00:00:00', 'America/Lima')->addMinutes($i),
                'motivo' => 'Histórico paginado',
                'estado' => AsignarCliente::ESTADO_APROBADA,
                'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
            ]);
        }

        $report = app(AssignmentReportService::class);
        $historicalRow = $report->filteredQuery(['numdoc' => $historical->numdoc])->first();
        $this->assertSame('Histórico', $historicalRow->cliente);
        $this->assertSame('Sistema / Histórico', $historicalRow->solicitante);
        $this->assertSame('BOLSA', $historicalRow->asesor_destino);
        $this->assertSame('Histórico', $historicalRow->motivo);
        $this->assertSame('Sin información', $historicalRow->resuelto_por);

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones', ['numdoc' => $historical->numdoc]))
            ->assertOk()
            ->assertSee('Histórico')
            ->assertSee('BOLSA')
            ->assertSee('Sistema / Histórico');

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones', [
                'numdoc' => $manualAccount->numdoc,
                'operacion' => $manualAccount->operacion,
                'asesor' => $asesor->name,
                'estado' => AsignarCliente::ESTADO_APROBADA,
                'origen' => AsignarCliente::ORIGEN_MANUAL,
            ]))
            ->assertOk()
            ->assertSee('CLIENTE MANUAL REPORTE')
            ->assertDontSee('CLIENTE MISMA OPERACIÓN');

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones', ['numdoc' => '0000009999', 'page' => 2]))
            ->assertOk()
            ->assertSee('PAGE-01');

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones', ['numdoc' => 'SIN-RESULTADO']))
            ->assertOk()
            ->assertSee('No hay asignaciones para los filtros indicados.');

        $this->actingAs($asesor)
            ->get(route('reportes.asignaciones'))
            ->assertForbidden();

        $path = (new AssignmentExport($report->filteredQuery(['numdoc' => $manualAccount->numdoc])))->export();
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('ID', $sheet->getCell('A1')->getValue());
        $this->assertSame('Fecha solicitud', $sheet->getCell('B1')->getValue());
        $this->assertSame('Documento', $sheet->getCell('C1')->getValue());
        $this->assertSame('Operación', $sheet->getCell('E1')->getValue());
        $this->assertSame('Fecha resolución', $sheet->getCell('L1')->getValue());
        $this->assertSame('0000004321', $sheet->getCell('C2')->getValue());
        $this->assertSame('0000008765', $sheet->getCell('E2')->getValue());
        @unlink($path);

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones.export', ['numdoc' => $manualAccount->numdoc]))
            ->assertOk()
            ->assertDownload();
    }

    /**
     * 21. Prohibición de editar o eliminar físicamente asignaciones aprobadas o históricas.
     */
    public function test_approved_assignments_cannot_be_edited_or_deleted(): void
    {
        $admin = $this->makeUser('administrador');
        $asesor1 = $this->makeUser('asesor', ['name' => 'Asesor Uno']);
        $asesor2 = $this->makeUser('asesor', ['name' => 'Asesor Dos']);
        $cuenta = $this->createAccount('00443322', 'OP-INMUTABLE');

        $aprobada = AsignarCliente::query()->create([
            'numdoc' => '00443322',
            'operacion' => 'OP-INMUTABLE',
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $asesor1->name,
            'solicitante_user_id' => $admin->id,
            'asesor_destino_user_id' => $asesor1->id,
            'fecha_solicitud' => Carbon::now('America/Lima'),
            'motivo' => 'carga masiva',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::now('America/Lima'),
            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
        ]);

        // Intentar editar una asignación ya aprobada debe lanzar DomainException
        $this->expectException(DomainException::class);
        $aprobada->update(['asesor_destino_user_id' => $asesor2->id]);
    }

    public function test_physical_deletion_of_assignment_history_is_forbidden(): void
    {
        $admin = $this->makeUser('administrador');
        $cuenta = $this->createAccount('00443399', 'OP-NO-DELETE');

        $record = AsignarCliente::query()->create([
            'numdoc' => '00443399',
            'operacion' => 'OP-NO-DELETE',
            'cliente_cuenta_id' => $cuenta->id,
            'name' => $admin->name,
            'solicitante_user_id' => $admin->id,
            'asesor_destino_user_id' => $admin->id,
            'fecha_solicitud' => Carbon::now('America/Lima'),
            'motivo' => 'Registro permanente',
            'estado' => AsignarCliente::ESTADO_APROBADA,
            'aprobado_por_user_id' => $admin->id,
            'fecha_aprobacion' => Carbon::now('America/Lima'),
            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
        ]);

        $this->expectException(DomainException::class);
        $record->delete();
    }

    public function test_assignment_workflow_notifies_authorized_active_users_and_moves_history_to_reports(): void
    {
        $asesor = $this->makeUser('asesor', ['name' => 'Asesor solicitante']);
        $admin = $this->makeUser('administrador', ['name' => 'Admin resolutor']);
        $supervisor = $this->makeUser('supervisor');
        $soporte = $this->makeUser('soporte');
        $inactive = $this->makeUser('administrador', ['active' => 0]);
        $numdoc = '0022097866';
        $operation = '8190917';
        $this->createAccount($numdoc, $operation, 'TESTEO GISELLA');

        $this->actingAs($asesor)
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => [$operation],
                'motivo' => 'Solicitud para prueba de notificaciones.',
            ])
            ->assertRedirect(route('clientes.show', $numdoc));

        foreach ([$admin, $supervisor, $soporte] as $reviewer) {
            $this->assertSame(1, $reviewer->notifications()->where('data->type', 'asignacion_action_required')->count());
        }
        $this->assertSame(0, $inactive->notifications()->where('data->type', 'asignacion_action_required')->count());

        $assignment = AsignarCliente::query()->where('numdoc', $numdoc)->where('operacion', $operation)->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('notificaciones.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
        $notification = $admin->unreadNotifications()->firstOrFail();
        $this->assertSame('/autorizacion#panel-asignaciones', parse_url((string) $notification->data['action_url'], PHP_URL_PATH).'#'.parse_url((string) $notification->data['action_url'], PHP_URL_FRAGMENT));
        $this->actingAs($admin)
            ->postJson(route('notificaciones.leer', $notification->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)->get(route('autorizacion'))
            ->assertOk()
            ->assertSee('ASIGNAR CLIENTE')
            ->assertSee($operation)
            ->assertViewHas('asignacionRows', fn ($rows) => $rows->total() >= 1 && (int) $rows->first()->id === (int) $assignment->id);

        $this->actingAs($asesor)->get(route('clientes.show', $numdoc))
            ->assertOk()
            ->assertDontSee('Historial de asignaciones')
            ->assertViewMissing('asignacionesHistorial');

        $this->actingAs($admin)
            ->post(route('autorizacion.asignaciones.aprobar', $assignment), ['nota_estado' => 'Aprobación de prueba'])
            ->assertRedirect();

        $this->assertDatabaseHas('asignar_clientes', ['id' => $assignment->id, 'estado' => AsignarCliente::ESTADO_APROBADA, 'aprobado_por_user_id' => $admin->id]);
        $this->assertSame(1, $asesor->notifications()->where('data->type', 'asignacion_status')->count());
        $this->actingAs($admin)->get(route('autorizacion'))
            ->assertOk()
            ->assertDontSee($operation);

        $rejectedOperation = '8190918';
        $this->createAccount($numdoc, $rejectedOperation, 'TESTEO GISELLA');
        $this->actingAs($asesor)
            ->post(route('clientes.asignaciones.store', $numdoc), [
                'operaciones' => [$rejectedOperation],
                'motivo' => 'Solicitud destinada a verificar el rechazo.',
            ])
            ->assertRedirect(route('clientes.show', $numdoc));
        $rejected = AsignarCliente::query()->where('numdoc', $numdoc)->where('operacion', $rejectedOperation)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('autorizacion.asignaciones.rechazar', $rejected), ['nota_estado' => 'No corresponde a la cartera.'])
            ->assertRedirect();
        $this->assertSame(1, $asesor->notifications()->where('data->type', 'asignacion_status')->where('data->estado', AsignarCliente::ESTADO_RECHAZADA)->count());

        $this->actingAs($asesor)
            ->get(route('reportes.asignaciones'))
            ->assertForbidden();

        $this->actingAs($admin)->get(route('reportes.asignaciones', ['numdoc' => $numdoc]))
            ->assertOk()
            ->assertSee('Historial de asignaciones')
            ->assertSee($operation)
            ->assertSee('Aprobada');

        $this->actingAs($admin)
            ->get(route('reportes.asignaciones.export', ['numdoc' => $numdoc]))
            ->assertOk()
            ->assertDownload();
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test ' . ucfirst($role) . ' ' . Str::random(6),
            'email' => 'test-' . $role . '-' . Str::uuid() . '@kpinvest.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createAccount(
        string $numdoc,
        string $operacion,
        string $nombre = 'CLIENTE PRUEBA ASIGNACION',
        string $entidad = 'BCP'
    ): ClienteCuenta {
        return ClienteCuenta::query()->create([
            'numdoc' => $numdoc,
            'cuenta' => 'CTA-' . $operacion,
            'operacion' => $operacion,
            'nombre' => $nombre,
            'entidad' => $entidad,
            'producto' => 'PRESTAMO PERSONAL',
            'cosecha' => '2025',
            'moneda' => 'PEN',
            'deuda_capital' => 1500.00,
            'interes' => 200.00,
            'deuda_total' => 1700.00,
        ]);
    }
}
