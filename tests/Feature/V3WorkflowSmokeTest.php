<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\PagoLote;
use App\Models\PagoPropia;
use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class V3WorkflowSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private array $filesToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToDelete as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_admin_user_post_actions_work(): void
    {
        $admin = $this->makeUser('administrador');
        $target = $this->makeUser('asesor');

        $newEmail = 'v3-created-'.Str::uuid().'@example.test';

        $this->actingAs($admin)
            ->post(route('administracion.usuarios.store'), [
                'name' => 'V3 Created User',
                'email' => $newEmail,
                'role' => 'asesor',
                'password' => 'secret123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $newEmail,
            'role' => 'asesor',
            'active' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('administracion.usuarios.toggle', $target), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'active' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('administracion.usuarios.password', $target), [
                'password' => 'new-secret-v3',
                'password_confirmation' => 'new-secret-v3',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-v3', $target->fresh()->password));
    }

    public function test_promesa_creation_and_workflow_posts_work(): void
    {
        Mail::fake();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operacion] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'cancelacion',
                'telefono' => '999111222',
                'operaciones' => [$operacion],
                'fecha_pago' => now()->addDays(3)->toDateString(),
                'monto_cancel' => '100.00',
                'nota' => 'V3 cancelacion smoke',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('promesas_pago', [
            'dni' => $dni,
            'tipo' => 'cancelacion',
            'workflow_estado' => 'pendiente',
        ]);

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'convenio',
                'telefono' => '999111222',
                'operaciones' => [$operacion],
                'nro_cuotas' => 2,
                'monto_convenio' => '200.00',
                'cron_fecha' => [now()->addDays(5)->toDateString(), now()->addDays(35)->toDateString()],
                'cron_monto' => ['100.00', '100.00'],
                'nota' => 'V3 convenio smoke',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $convenio = PromesaPago::where('dni', $dni)->where('nota', 'V3 convenio smoke')->firstOrFail();
        $this->assertSame(2, DB::table('promesa_cuotas')->where('promesa_id', $convenio->id)->count());

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'convenio_balon',
                'telefono' => '999111222',
                'operaciones' => [$operacion],
                'nro_cuotas' => 2,
                'monto_convenio' => '300.00',
                'cron_fecha' => [now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString()],
                'cron_monto' => ['100.00', '200.00'],
                'cron_balon' => 2,
                'nota' => 'V3 cuota balon smoke',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $balon = PromesaPago::where('dni', $dni)->where('nota', 'V3 cuota balon smoke')->firstOrFail();
        $this->assertDatabaseHas('promesa_cuotas', [
            'promesa_id' => $balon->id,
            'nro' => 2,
            'es_balon' => 1,
        ]);

        $pending = $this->createPromesa($dni, $operacion, $asesor, 'pendiente');
        $this->actingAs($supervisor)
            ->post(route('autorizacion.preaprobar', $pending), ['nota_estado' => 'ok sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('preaprobada', $pending->fresh()->workflow_estado);

        $toRejectBySupervisor = $this->createPromesa($dni, $operacion, $asesor, 'pendiente');
        $this->actingAs($supervisor)
            ->post(route('autorizacion.rechazar.sup', $toRejectBySupervisor), ['nota_estado' => 'rechazo sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada_sup', $toRejectBySupervisor->fresh()->workflow_estado);

        $preApproved = $this->createPromesa($dni, $operacion, $asesor, 'preaprobada', $supervisor);
        $this->actingAs($admin)
            ->post(route('autorizacion.aprobar', $preApproved), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('aprobada', $preApproved->fresh()->workflow_estado);

        $this->ensurePromesaTemplate();
        $this->actingAs($admin)
            ->get(route('promesas.acuerdo', $preApproved->fresh()))
            ->assertOk();
        $this->filesToDelete[] = storage_path('app/tmp/Conv_'.$dni.'.docx');
        $this->filesToDelete[] = storage_path('app/tmp/Conv_'.$dni.'.pdf');

        $toRejectByAdmin = $this->createPromesa($dni, $operacion, $asesor, 'preaprobada', $supervisor);
        $this->actingAs($admin)
            ->post(route('autorizacion.rechazar.admin', $toRejectByAdmin), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $toRejectByAdmin->fresh()->workflow_estado);
    }

    public function test_cna_creation_workflow_and_download_fallback_work(): void
    {
        Mail::fake();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operacion] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.cna.store', $dni), [
                'titular' => 'Cliente V3 Test',
                'fecha_pago_realizado' => now()->toDateString(),
                'monto_pagado' => '150.00',
                'operaciones' => [$operacion],
                'observacion' => 'CNA smoke',
            ])
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cna_solicitudes', [
            'dni' => $dni,
            'workflow_estado' => 'pendiente',
        ]);

        $pending = $this->createCna($dni, $operacion, $asesor, 'pendiente');
        $this->actingAs($supervisor)
            ->post(route('cna.preaprobar', $pending), ['nota_estado' => 'ok sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('preaprobada', $pending->fresh()->workflow_estado);

        $toRejectBySupervisor = $this->createCna($dni, $operacion, $asesor, 'pendiente');
        $this->actingAs($supervisor)
            ->post(route('cna.rechazar.sup', $toRejectBySupervisor), ['nota_estado' => 'rechazo sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada_sup', $toRejectBySupervisor->fresh()->workflow_estado);

        $this->ensureCnaTemplate();

        $preApproved = $this->createCna($dni, $operacion, $asesor, 'preaprobada', $supervisor);
        $this->actingAs($admin)
            ->post(route('cna.aprobar', $preApproved), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $approved = $preApproved->fresh();
        $this->assertSame('aprobada', $approved->workflow_estado);
        $this->assertNotEmpty($approved->docx_path);

        $docxAbs = storage_path('app/'.$approved->docx_path);
        $this->filesToDelete[] = $docxAbs;

        $this->actingAs($admin)->get(route('cna.docx', $approved))->assertOk();
        $this->actingAs($admin)->get(route('cna.pdf', $approved))->assertOk();

        $toRejectByAdmin = $this->createCna($dni, $operacion, $asesor, 'preaprobada', $supervisor);
        $this->actingAs($admin)
            ->post(route('cna.rechazar.admin', $toRejectByAdmin), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $toRejectByAdmin->fresh()->workflow_estado);
    }

    public function test_reports_exports_and_csv_imports_work_with_small_local_files(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('administrador');
        [$dni, $operacion] = $this->createClientAccount();

        PagoLote::create([
            'tipo' => 'propia',
            'archivo' => 'v3-smoke.csv',
            'usuario_id' => $admin->id,
            'total_registros' => 1,
        ])->pagosPropia()->create([
            'dni' => $dni,
            'operacion' => $operacion,
            'entidad' => 'BBVA',
            'nombre_cliente' => 'Cliente V3 Test',
            'monto_pagado' => 77.50,
            'fecha' => now()->toDateString(),
            'gestor' => 'Gestor V3',
            'cosecha' => 'BBVA1',
            'cuenta_recaudo' => 'CTA-V3',
        ]);

        $this->actingAs($admin)
            ->get(route('reportes.pagos.export', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'q' => $dni,
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reportes.pagos', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'q' => $dni,
                'page' => 1,
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('reportes.pagos.facets', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'q' => $dni,
            ]))
            ->assertOk()
            ->assertJsonStructure(['gestores', 'cosechas', 'entidades']);

        $promesa = $this->createPromesa($dni, $operacion, $admin, 'aprobada');
        $this->actingAs($admin)
            ->get(route('reportes.pdp.export', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'q' => $dni,
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reportes.pdp', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'q' => $dni,
                'partial' => 1,
                'page' => 1,
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $cna = $this->createCna($dni, $operacion, $admin, 'aprobada');
        $this->actingAs($admin)
            ->get(route('reportes.cna.export', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'estado' => ['aprobada'],
                'gestor' => [$admin->name],
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reportes.cna', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'estado' => ['aprobada'],
                'gestor' => [$admin->name],
                'page' => 1,
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('integracion.pagos.import'), [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'pagos.csv',
                    "Fecha,DNI,Nombre,Operacion,Monto,Agente,Cosecha,Cuenta_Recaudo,Entidad Financiera\n".
                    now()->format('d/m/Y').",{$dni},Cliente V3 Test,{$operacion},88.90,Gestor V3,BBVA1,CTA-V3,BBVA\n"
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pagos_propia', [
            'dni' => $dni,
            'operacion' => $operacion,
            'monto_pagado' => 88.90,
        ]);

        $newDni = 'V3D'.Str::upper(Str::random(8));
        $newOperation = 'V3OP'.Str::upper(Str::random(8));

        $this->actingAs($admin)
            ->post(route('integracion.data.import'), [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'data.csv',
                    "NUMDOC,CUENTA,OPERACION,NOMBRE,PRODUCTO,DPTO,PROVINCIA,DISTRITO,DIRECCION,ENTIDAD,COSECHA,FECHA_COMPRA,FECHA_CASTIGO,MONEDA,DEUDA_CAPITAL,INTERES,DEUDA_TOTAL\n".
                    "{$newDni},CTA-NEW,{$newOperation},Cliente Importado,Producto,Lima,Lima,Lima,Direccion,BBVA,BBVA1,2024-01-01,2024-02-01,PEN,10,1,11\n"
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes_cuentas', [
            'numdoc' => $newDni,
            'operacion' => $newOperation,
        ]);

        $this->actingAs($admin)
            ->post(route('integracion.asignacion.import'), [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'asignacion.csv',
                    "NUMDOC,OPERACION,NAME\n{$dni},{$operacion},Gestor V3\n"
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asignar_clientes', [
            'numdoc' => $dni,
            'operacion' => $operacion,
            'name' => 'Gestor V3',
        ]);

        $this->actingAs($admin)
            ->post(route('integracion.ccd.import'), [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'ccd.csv',
                    "NUMDOC,PDF,COSECHA,LINK\n{$dni},https://example.test/doc.pdf,BBVA1,https://example.test/link\n"
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ccd_clientes', [
            'numdoc' => $dni,
            'cosecha' => 'BBVA1',
        ]);

        $this->assertNotNull($promesa->id);
        $this->assertNotNull($cna->id);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 '.$role.' '.Str::random(8),
            'email' => 'v3-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3D'.Str::upper(Str::random(8));
        $operation = 'V3OP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente V3 Test',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion V3',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dni, $operation];
    }

    private function createPromesa(
        string $dni,
        string $operacion,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): PromesaPago {
        return PromesaPago::create([
            'dni' => $dni,
            'operacion' => $operacion,
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'monto' => 120,
            'workflow_estado' => $state,
            'tipo' => 'cancelacion',
            'telefono' => '999111222',
            'nota' => 'V3 workflow smoke',
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
            'aprobado_por' => $state === 'aprobada' ? $owner->id : null,
            'aprobado_at' => $state === 'aprobada' ? now() : null,
        ]);
    }

    private function createCna(
        string $dni,
        string $operacion,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): CnaSolicitud {
        return CnaSolicitud::create([
            'correlativo' => random_int(100000, 999999),
            'nro_carta' => 'V3'.Str::upper(Str::random(10)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA workflow smoke',
            'dni' => $dni,
            'titular' => 'Cliente V3 Test',
            'producto' => 'Producto V3',
            'operaciones' => [$operacion],
            'workflow_estado' => $state,
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
            'aprobado_por' => $state === 'aprobada' ? $owner->id : null,
            'aprobado_at' => $state === 'aprobada' ? now() : null,
        ]);
    }

    private function ensureCnaTemplate(): void
    {
        $dir = storage_path('app/templates');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir.'/cna_kpinvest.docx';
        if (is_file($path)) {
            return;
        }

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('${nro_carta} ${nombre} ${numdoc} ${aprobado_at}');
        $section->addText('${operacion} ${cuenta} ${entidad}');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $this->filesToDelete[] = $path;
    }

    private function ensurePromesaTemplate(): void
    {
        $dir = storage_path('app/templates');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir.'/Acuerdo_de_Pago_DNI_{dni}.docx';
        if (is_file($path)) {
            return;
        }

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('${name} ${id} ${created_at} ${nombre} ${numdoc} ${telefono} ${direccion} ${entidad} ${monto}');

        $ops = $section->addTable();
        $ops->addRow();
        $ops->addCell()->addText('${operacion}');
        $ops->addCell()->addText('${deuda_total}');
        $ops->addCell()->addText('${monto_divido}');

        $cuotas = $section->addTable();
        $cuotas->addRow();
        $cuotas->addCell()->addText('${nro_cuotas}');
        $cuotas->addCell()->addText('${monto_cuota}');
        $cuotas->addCell()->addText('${fecha_pago}');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $this->filesToDelete[] = $path;
    }
}
