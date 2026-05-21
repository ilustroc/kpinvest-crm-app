<?php

namespace Tests\Feature;

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

class V3NotificationModuleTest extends TestCase
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

    public function test_promesa_creation_creates_internal_notifications_by_role(): void
    {
        Mail::fake();

        [$admin, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa asesor notification'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asesorPromesa = PromesaPago::where('nota', 'Promesa asesor notification')->firstOrFail();
        $this->assertSame('pendiente', $asesorPromesa->workflow_estado);
        $this->assertActionNotification($supervisor, 'promesa_action_required', 'Abrir autorizacion', '/autorizacion', '/clientes/');

        $this->actingAs($supervisor)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa supervisor notification'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $supervisorPromesa = PromesaPago::where('nota', 'Promesa supervisor notification')->firstOrFail();
        $this->assertSame('preaprobada', $supervisorPromesa->workflow_estado);
        $this->assertActionNotification($admin, 'promesa_action_required', 'Abrir autorizacion', '/autorizacion', '/clientes/');

        $this->actingAs($admin)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa admin notification'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $adminPromesa = PromesaPago::where('nota', 'Promesa admin notification')->firstOrFail();
        $this->assertSame('aprobada', $adminPromesa->workflow_estado);
        $this->assertActionNotification($admin, 'promesa_status', 'Ver estado', '/clientes/'.$dni, '/autorizacion');
    }

    public function test_cna_creation_creates_internal_notifications_by_role(): void
    {
        Mail::fake();
        $this->forceDocxFallback();
        $this->ensureCnaTemplate();

        [$admin, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA asesor notification'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $asesorCna = CnaSolicitud::where('observacion', 'CNA asesor notification')->firstOrFail();
        $this->assertSame('pendiente', $asesorCna->workflow_estado);
        $this->assertActionNotification($supervisor, 'cna_action_required', 'Abrir autorizacion', '/autorizacion', '/clientes/');

        $this->actingAs($supervisor)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA supervisor notification'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $supervisorCna = CnaSolicitud::where('observacion', 'CNA supervisor notification')->firstOrFail();
        $this->assertSame('preaprobada', $supervisorCna->workflow_estado);
        $this->assertActionNotification($admin, 'cna_action_required', 'Abrir autorizacion', '/autorizacion', '/clientes/');

        $this->actingAs($admin)
            ->post(route('clientes.cna.store', $dni), $this->cnaPayload($operation, 'CNA admin notification'))
            ->assertRedirect(route('clientes.show', $dni))
            ->assertSessionHasNoErrors();

        $adminCna = CnaSolicitud::where('observacion', 'CNA admin notification')->firstOrFail();
        $this->assertSame('aprobada', $adminCna->workflow_estado);
        $this->assertActionNotification($admin, 'cna_status', 'Ver estado', '/clientes/'.$dni, '/autorizacion');
        $this->filesToDelete[] = storage_path('app/'.$adminCna->docx_path);
    }

    public function test_notification_routes_list_count_and_mark_read_for_owner_only(): void
    {
        Mail::fake();

        [, $supervisor, $asesor] = $this->workflowUsers();
        [$dni, $operation] = $this->createClientAccount();

        $this->actingAs($asesor)
            ->post(route('clientes.promesas.store', $dni), $this->promesaPayload($operation, 'Promesa endpoints notification'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $notification = $supervisor->fresh()->unreadNotifications()->firstOrFail();

        $count = $this->actingAs($supervisor)
            ->getJson(route('notificaciones.unread-count'))
            ->assertOk()
            ->json('unread_count');

        $this->assertGreaterThanOrEqual(1, $count);

        $this->actingAs($supervisor)
            ->getJson(route('notificaciones.index'))
            ->assertOk()
            ->assertJsonStructure([
                'unread_count',
                'notifications' => [
                    '*' => ['id', 'type', 'title', 'message', 'action_label', 'action_url', 'is_read'],
                ],
            ]);

        $this->actingAs($asesor)
            ->postJson(route('notificaciones.leer', $notification->id))
            ->assertNotFound();

        $this->actingAs($supervisor)
            ->postJson(route('notificaciones.leer', $notification->id))
            ->assertOk()
            ->assertJsonPath('notification.is_read', true);

        $this->assertSame(0, $supervisor->fresh()->unreadNotifications()->whereKey($notification->id)->count());

        $this->actingAs($supervisor)
            ->postJson(route('notificaciones.leer-todas'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_resolution_notifications_are_informative_and_point_to_client_status(): void
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

        $this->assertActionNotification($asesor, 'promesa_status', 'Ver estado', '/clientes/'.$dni, '/autorizacion');

        $cna = $this->createCna($dni, $operation, $asesor, 'preaprobada', $supervisor);

        $this->actingAs($admin)
            ->post(route('cna.rechazar.admin', $cna), ['nota_estado' => 'rechazo admin'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertActionNotification($asesor, 'cna_status', 'Ver estado', '/clientes/'.$dni, '/autorizacion');
    }

    private function assertActionNotification(
        User $user,
        string $type,
        string $actionLabel,
        string $urlContains,
        string $urlNotContains
    ): void {
        $notification = $user->fresh()->notifications()
            ->get()
            ->first(function ($notification) use ($type, $actionLabel, $urlContains, $urlNotContains) {
                $data = (array) $notification->data;

                return ($data['type'] ?? null) === $type
                    && ($data['action_label'] ?? null) === $actionLabel
                    && str_contains((string) ($data['action_url'] ?? ''), $urlContains)
                    && ! str_contains((string) ($data['action_url'] ?? ''), $urlNotContains);
            });

        $this->assertNotNull(
            $notification,
            "Expected notification {$type} for {$user->email}."
        );
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
            'name' => 'V3 Notification '.$role.' '.Str::random(8),
            'email' => 'v3n-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ], $attributes));
    }

    private function createClientAccount(): array
    {
        $dni = 'V3NOT'.Str::upper(Str::random(8));
        $operation = 'V3NOTOP'.Str::upper(Str::random(8));

        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => 'CTA-'.$operation,
            'nombre' => 'Cliente Notification Workflow V3',
            'dpto' => 'Lima',
            'operacion' => $operation,
            'entidad' => 'BBVA',
            'producto' => 'Producto Notification V3',
            'cosecha' => 'BBVA1',
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Direccion Notification V3',
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
            'titular' => 'Cliente Notification Workflow V3',
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
            'nota' => 'V3 notification workflow',
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
            'nro_carta' => 'V3NOT'.Str::upper(Str::random(8)),
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 150,
            'observacion' => 'CNA notification workflow',
            'dni' => $dni,
            'titular' => 'Cliente Notification Workflow V3',
            'producto' => 'Producto Notification V3',
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
