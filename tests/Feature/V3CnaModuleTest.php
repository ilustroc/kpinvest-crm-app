<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class V3CnaModuleTest extends TestCase
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

    public function test_cna_creation_keeps_pending_and_admin_auto_approved_behavior(): void
    {
        Mail::fake();
        $this->forceDocxFallback();
        $this->ensureCnaTemplate();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.cna.store', $dni), [
                'titular' => 'Cliente CNA V3',
                'fecha_pago_realizado' => now()->toDateString(),
                'monto_pagado' => '150.00',
                'operaciones' => [$operation],
                'observacion' => 'CNA V3 pendiente',
            ])
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cna_solicitudes', [
            'dni' => $dni,
            'observacion' => 'CNA V3 pendiente',
            'workflow_estado' => 'pendiente',
            'user_id' => $asesor->id,
        ]);

        $this->actingAs($admin)
            ->post(route('clientes.cna.store', $dni), [
                'titular' => 'Cliente CNA V3',
                'fecha_pago_realizado' => now()->toDateString(),
                'monto_pagado' => '250.00',
                'operaciones' => [$operation],
                'observacion' => 'CNA V3 auto admin',
            ])
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $autoApproved = CnaSolicitud::where('dni', $dni)
            ->where('observacion', 'CNA V3 auto admin')
            ->firstOrFail();

        $this->assertSame('aprobada', $autoApproved->workflow_estado);
        $this->assertSame((int) $admin->id, (int) $autoApproved->aprobado_por);
        $this->assertNotEmpty($autoApproved->docx_path);
        $this->filesToDelete[] = storage_path('app/'.$autoApproved->docx_path);
    }

    public function test_cna_workflow_permissions_downloads_and_fallback_are_preserved(): void
    {
        Mail::fake();
        $this->forceDocxFallback();
        $this->ensureCnaTemplate();

        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);
        [$dni, $operation] = $this->createClientAccount();

        $pending = $this->createCna($dni, $operation, $asesor, 'pendiente');

        $this->actingAs($asesor)
            ->post(route('cna.preaprobar', $pending), ['nota_estado' => 'intento asesor'])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('cna.preaprobar', $pending), ['nota_estado' => 'ok supervisor'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('preaprobada', $pending->fresh()->workflow_estado);

        $toRejectBySupervisor = $this->createCna($dni, $operation, $asesor, 'pendiente');

        $this->actingAs($supervisor)
            ->post(route('cna.rechazar.sup', $toRejectBySupervisor), ['nota_estado' => 'rechazo supervisor'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazada_sup', $toRejectBySupervisor->fresh()->workflow_estado);

        $preApproved = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($supervisor)
            ->post(route('cna.aprobar', $preApproved), ['nota_estado' => 'intento supervisor'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('cna.aprobar', $preApproved), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $approved = $preApproved->fresh();
        $this->assertSame('aprobada', $approved->workflow_estado);
        $this->assertNotEmpty($approved->docx_path);
        $this->filesToDelete[] = storage_path('app/'.$approved->docx_path);

        $this->actingAs($admin)
            ->get(route('cna.docx', $approved))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('cna.pdf', $approved))
            ->assertOk();

        $toRejectByAdmin = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('cna.rechazar.admin', $toRejectByAdmin), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazada', $toRejectByAdmin->fresh()->workflow_estado);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 '.$role.' '.Str::random(8),
            'email' => 'v3-cna-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3CNA'.Str::upper(Str::random(8));
        $operation = 'V3CNAOP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente CNA V3',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto CNA V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion CNA V3',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dni, $operation];
    }

    private function createCna(
        string $dni,
        string $operation,
        User $owner,
        string $state,
        ?User $supervisor = null
    ): CnaSolicitud {
        return CnaSolicitud::create([
            'correlativo' => random_int(100000, 999999),
            'nro_carta' => 'V3CNA'.Str::upper(Str::random(8)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA V3 module',
            'dni' => $dni,
            'titular' => 'Cliente CNA V3',
            'producto' => 'Producto CNA V3',
            'operaciones' => [$operation],
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

        if (! is_dir($dir)) {
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

    private function forceDocxFallback(): void
    {
        config([
            'services.ilovepdf.public' => null,
            'services.ilovepdf.secret' => null,
        ]);
    }
}
