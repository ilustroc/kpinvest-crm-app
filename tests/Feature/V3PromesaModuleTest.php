<?php

namespace Tests\Feature;

use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class V3PromesaModuleTest extends TestCase
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

    public function test_promesa_creation_keeps_cancelacion_convenio_and_balon_behavior(): void
    {
        Mail::fake();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'cancelacion',
                'telefono' => '999111222',
                'operaciones' => [$operation],
                'fecha_pago' => now()->addDays(3)->toDateString(),
                'monto_cancel' => '100.00',
                'nota' => 'V3 promesa cancelacion',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('promesas_pago', [
            'dni' => $dni,
            'tipo' => 'cancelacion',
            'workflow_estado' => 'pendiente',
            'nota' => 'V3 promesa cancelacion',
        ]);

        $this->actingAs($supervisor)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'convenio',
                'telefono' => '999111222',
                'operaciones' => [$operation],
                'nro_cuotas' => 2,
                'monto_convenio' => '200.00',
                'cron_fecha' => [now()->addDays(5)->toDateString(), now()->addDays(35)->toDateString()],
                'cron_monto' => ['100.00', '100.00'],
                'nota' => 'V3 promesa convenio',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $convenio = PromesaPago::where('dni', $dni)->where('nota', 'V3 promesa convenio')->firstOrFail();
        $this->assertSame('preaprobada', $convenio->workflow_estado);
        $this->assertSame(2, DB::table('promesa_cuotas')->where('promesa_id', $convenio->id)->count());

        $this->actingAs($admin)
            ->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'convenio_balon',
                'telefono' => '999111222',
                'operaciones' => [$operation],
                'nro_cuotas' => 2,
                'monto_convenio' => '300.00',
                'cron_fecha' => [now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString()],
                'cron_monto' => ['100.00', '200.00'],
                'cron_balon' => 2,
                'nota' => 'V3 promesa balon',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $balon = PromesaPago::where('dni', $dni)->where('nota', 'V3 promesa balon')->firstOrFail();
        $this->assertSame('aprobada', $balon->workflow_estado);
        $this->assertSame('convenio', $balon->tipo);
        $this->assertDatabaseHas('promesa_cuotas', [
            'promesa_id' => $balon->id,
            'nro' => 2,
            'es_balon' => 1,
        ]);
    }

    public function test_promesa_workflow_permissions_and_states_are_preserved(): void
    {
        Mail::fake();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $pending = $this->createPromesa($dni, $operation, $asesor, 'pendiente');

        $this->actingAs($asesor)
            ->post(route('autorizacion.preaprobar', $pending), ['nota_estado' => 'asesor no'])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('autorizacion.preaprobar', $pending), ['nota_estado' => 'ok sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('preaprobada', $pending->fresh()->workflow_estado);

        $toRejectBySupervisor = $this->createPromesa($dni, $operation, $asesor, 'pendiente');

        $this->actingAs($supervisor)
            ->post(route('autorizacion.rechazar.sup', $toRejectBySupervisor), ['nota_estado' => 'rechazo sup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazada_sup', $toRejectBySupervisor->fresh()->workflow_estado);

        $preApproved = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('autorizacion.aprobar', $preApproved), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('aprobada', $preApproved->fresh()->workflow_estado);

        $toRejectByAdmin = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('autorizacion.rechazar.admin', $toRejectByAdmin), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazada', $toRejectByAdmin->fresh()->workflow_estado);
    }

    public function test_promesa_agreement_download_falls_back_to_docx_without_ilovepdf_keys(): void
    {
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();
        $promesa = $this->createPromesa($dni, $operation, $admin, 'aprobada');

        $this->ensurePromesaTemplate();

        $this->actingAs($admin)
            ->get(route('promesas.acuerdo', $promesa))
            ->assertOk();

        $this->filesToDelete[] = storage_path('app/tmp/Conv_'.$dni.'.docx');
        $this->filesToDelete[] = storage_path('app/tmp/Conv_'.$dni.'.pdf');
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 Promesa '.$role.' '.Str::random(8),
            'email' => 'v3-promesa-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3P'.Str::upper(Str::random(8));
        $operation = 'V3POP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente V3 Promesa',
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
        string $operation,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): PromesaPago {
        $promesa = PromesaPago::create([
            'dni' => $dni,
            'operacion' => $operation,
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'monto' => 120,
            'workflow_estado' => $state,
            'tipo' => 'cancelacion',
            'telefono' => '999111222',
            'nota' => 'V3 promesa module',
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
            'aprobado_por' => $state === 'aprobada' ? $owner->id : null,
            'aprobado_at' => $state === 'aprobada' ? now() : null,
        ]);

        DB::table('promesa_operaciones')->insert([
            'promesa_id' => $promesa->id,
            'operacion' => $operation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $promesa;
    }

    private function ensurePromesaTemplate(): void
    {
        $dir = storage_path('app/templates');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir.'/Acuerdo_de_Pago_DNI_{dni}.docx';
        if (is_file($path)) {
            return;
        }

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('${name} ${id} ${created_at} ${nombre} ${numdoc} ${telefono} ${direccion} ${entidad} ${monto}');

        $operations = $section->addTable();
        $operations->addRow();
        $operations->addCell()->addText('${operacion}');
        $operations->addCell()->addText('${deuda_total}');
        $operations->addCell()->addText('${monto_divido}');

        $quotas = $section->addTable();
        $quotas->addRow();
        $quotas->addCell()->addText('${nro_cuotas}');
        $quotas->addCell()->addText('${monto_cuota}');
        $quotas->addCell()->addText('${fecha_pago}');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $this->filesToDelete[] = $path;
    }
}
