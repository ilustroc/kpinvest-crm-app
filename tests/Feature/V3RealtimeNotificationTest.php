<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Workflow\WorkflowActionRequiredNotification;
use App\Notifications\Workflow\WorkflowStatusNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class V3RealtimeNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_workflow_notifications_include_broadcast_channel_and_payload(): void
    {
        $user = $this->makeUser('supervisor');
        $payload = $this->payload('promesa_action_required', 'Abrir autorizacion', '/autorizacion?tipo=promesa&id=10');

        $notification = new WorkflowActionRequiredNotification($payload);

        $this->assertContains('database', $notification->via($user));
        $this->assertContains('broadcast', $notification->via($user));
        $this->assertSame('Abrir autorizacion', $notification->toBroadcast($user)->data['action_label']);
        $this->assertStringContainsString('/autorizacion', $notification->toBroadcast($user)->data['action_url']);

        $status = new WorkflowStatusNotification($this->payload('promesa_status', 'Ver estado', '/clientes/12345678'));

        $this->assertContains('database', $status->via($user));
        $this->assertContains('broadcast', $status->via($user));
        $this->assertSame('Ver estado', $status->toBroadcast($user)->data['action_label']);
        $this->assertStringContainsString('/clientes/12345678', $status->toBroadcast($user)->data['action_url']);
    }

    public function test_user_private_notification_channel_only_authorizes_owner(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'local',
            'broadcasting.connections.reverb.secret' => 'local',
            'broadcasting.connections.reverb.app_id' => 'local',
        ]);
        Broadcast::forgetDrivers();
        Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

        $owner = $this->makeUser('supervisor');
        $other = $this->makeUser('asesor');

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-App.Models.User.'.$owner->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();

        $this->actingAs($other)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-App.Models.User.'.$owner->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    private function payload(string $type, string $label, string $url): array
    {
        return [
            'type' => $type,
            'module' => str_starts_with($type, 'cna') ? 'cna' : 'promesa',
            'entity_id' => 10,
            'dni' => '12345678',
            'cliente' => 'Cliente Broadcast V3',
            'estado' => 'pendiente',
            'title' => 'Notificacion realtime',
            'message' => 'Payload de prueba realtime.',
            'action_label' => $label,
            'action_url' => $url,
            'created_by' => 1,
            'created_by_name' => 'Usuario V3',
        ];
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'V3 Realtime '.$role.' '.Str::random(8),
            'email' => 'v3rt-'.$role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'active' => 1,
        ]);
    }
}
