<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class V3LocalFlowTest extends TestCase
{
    public function test_authenticated_core_pages_render_against_local_database(): void
    {
        $user = $this->requireActiveUser(['administrador', 'supervisor']);

        foreach ([
            '/',
            '/dashboard',
            '/administracion',
            '/autorizacion',
            '/reportes/pagos',
            '/reportes/promesas',
            '/reportes/cna',
        ] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_import_pages_render_against_local_database(): void
    {
        $user = $this->requireActiveUser(['administrador', 'supervisor', 'soporte']);

        foreach ([
            '/integracion/pagos',
            '/integracion/data',
            '/integracion/asignacion',
            '/integracion/ccd',
        ] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_client_search_and_profile_render_against_local_database(): void
    {
        $user = $this->requireActiveUser();
        $dni = $this->requireSampleDni();

        $this->actingAs($user)
            ->get('/clientes/suggest?q='.$dni)
            ->assertOk()
            ->assertJsonStructure([
                '*' => ['dni', 'nombre', 'operacion', 'cosecha', 'url'],
            ]);

        $this->actingAs($user)
            ->get('/clientes/lookup?q='.$dni)
            ->assertRedirect(route('clientes.show', $dni));

        $this->actingAs($user)
            ->get(route('clientes.show', $dni))
            ->assertOk();
    }

    private function requireActiveUser(array $roles = []): User
    {
        try {
            $query = User::query()->where('active', 1);

            if ($roles !== []) {
                $query->whereIn('role', $roles);
            }

            $user = $query->orderBy('id')->first();
        } catch (Throwable $e) {
            $this->markTestSkipped('Base local no disponible para smoke tests V3: '.$e->getMessage());
        }

        if (!$user) {
            $this->markTestSkipped('No hay usuario local activo para smoke tests V3.');
        }

        return $user;
    }

    private function requireSampleDni(): string
    {
        try {
            $dni = DB::table('clientes_cuentas')->whereNotNull('numdoc')->value('numdoc');
        } catch (Throwable $e) {
            $this->markTestSkipped('Tabla clientes_cuentas no disponible: '.$e->getMessage());
        }

        if (!$dni) {
            $this->markTestSkipped('No hay DNI local para validar cliente.');
        }

        return (string) $dni;
    }
}
