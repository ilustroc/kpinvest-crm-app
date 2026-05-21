<?php

namespace Tests\Feature;

use App\Mail\WorkflowMail;
use App\Models\CnaSolicitud;
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

class V3MailWorkflowTest extends TestCase
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

    public function test_promesa_creation_mails_match_creator_role_workflow(): void
    {
        Mail::fake();

        [$admin, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa asesor mail'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asesorPromesa = PromesaPago::where('nota', 'Promesa asesor mail')->firstOrFail();
        $this->assertSame('pendiente', $asesorPromesa->workflow_estado);
        $this->assertActionMail($supervisor->email, 'Abrir autorización', '/autorizacion', '/clientes/');

        $this->actingAs($supervisor)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa supervisor mail'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $supervisorPromesa = PromesaPago::where('nota', 'Promesa supervisor mail')->firstOrFail();
        $this->assertSame('preaprobada', $supervisorPromesa->workflow_estado);
        $this->assertActionMail($admin->email, 'Abrir autorización', '/autorizacion', '/clientes/');

        $this->actingAs($admin)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa admin mail'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $adminPromesa = PromesaPago::where('nota', 'Promesa admin mail')->firstOrFail();
        $this->assertSame('aprobada', $adminPromesa->workflow_estado);
        $this->assertActionMail($admin->email, 'Ver estado', '/clientes/'.$dni, '/autorizacion');
    }

    public function test_cna_creation_mails_match_creator_role_workflow(): void
    {
        Mail::fake();
        $this->forceDocxFallback();
        $this->ensureCnaTemplate();

        [$admin, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA asesor mail'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $asesorCna = CnaSolicitud::where('observacion', 'CNA asesor mail')->firstOrFail();
        $this->assertSame('pendiente', $asesorCna->workflow_estado);
        $this->assertActionMail($supervisor->email, 'Abrir autorización', '/autorizacion', '/clientes/');

        $this->actingAs($supervisor)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA supervisor mail'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $supervisorCna = CnaSolicitud::where('observacion', 'CNA supervisor mail')->firstOrFail();
        $this->assertSame('preaprobada', $supervisorCna->workflow_estado);
        $this->assertActionMail($admin->email, 'Abrir autorización', '/autorizacion', '/clientes/');

        $this->actingAs($admin)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA admin mail'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $adminCna = CnaSolicitud::where('observacion', 'CNA admin mail')->firstOrFail();
        $this->assertSame('aprobada', $adminCna->workflow_estado);
        $this->assertActionMail($admin->email, 'Ver estado', '/clientes/'.$dni, '/autorizacion');
        $this->filesToDelete[] = storage_path('app/'.$adminCna->docx_path);
    }

    public function test_resolution_mails_are_informative_and_point_to_client_status(): void
    {
        Mail::fake();
        $this->forceDocxFallback();
        $this->ensureCnaTemplate();

        [$admin, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $promesa = $this->createPromesa($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('autorizacion.aprobar', $promesa), ['nota_estado' => 'ok admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertActionMail($asesor->email, 'Ver estado', '/clientes/'.$dni, '/autorizacion');

        $cna = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('cna.rechazar.admin', $cna), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertActionMail($asesor->email, 'Ver estado', '/clientes/'.$dni, '/autorizacion');
    }

    private function assertActionMail(string $email, string $text, string $urlContains, string $urlNotContains): void
    {
        Mail::assertSent(WorkflowMail::class, function (WorkflowMail $mail) use ($email, $text, $urlContains, $urlNotContains) {
            return $mail->hasTo($email)
                && $mail->actionText === $text
                && str_contains((string) $mail->actionUrl, $urlContains)
                && ! str_contains((string) $mail->actionUrl, $urlNotContains);
        });
    }

    private function workflowUsers(): array
    {
        $admin = $this->makeUser('administrador');
        $supervisor = $this->makeUser('supervisor');
        $asesor = $this->makeUser('asesor', ['supervisor_id' => $supervisor->id]);

        return [$admin, $supervisor, $asesor];
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'V3 Mail '.$role.' '.Str::random(8),
            'email' => 'v3-mail-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3MAIL'.Str::upper(Str::random(8));
        $operation = 'V3MAILOP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente Mail Workflow V3',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto Mail V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion Mail V3',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dni, $operation];
    }

    private function promesaPayload(string $operation, string $note): array
    {
        return [
            'tipo' => 'cancelacion',
            'telefono' => '999111222',
            'operaciones' => [$operation],
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'monto_cancel' => '100.00',
            'nota' => $note,
        ];
    }

    private function cnaPayload(string $operation, string $note): array
    {
        return [
            'titular' => 'Cliente Mail Workflow V3',
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => '150.00',
            'operaciones' => [$operation],
            'observacion' => $note,
        ];
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
            'nota' => 'V3 mail workflow',
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
        ]);

        DB::table('promesa_operaciones')->insert([
            'promesa_id' => $promesa->id,
            'operacion' => $operation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $promesa;
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
            'nro_carta' => 'V3MAIL'.Str::upper(Str::random(8)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA mail workflow',
            'dni' => $dni,
            'titular' => 'Cliente Mail Workflow V3',
            'producto' => 'Producto Mail V3',
            'operaciones' => [$operation],
            'workflow_estado' => $state,
            'user_id' => $owner->id,
            'pre_aprobado_por' => $supervisor?->id,
            'pre_aprobado_at' => $supervisor ? now() : null,
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
