<?php

namespace Tests\Feature;

use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\PhysicalTerminalWorkflow;
use App\Actions\Attendance\PortalManager;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhysicalTerminalTest extends TestCase
{
    public function test_terminal_admin_pages_and_command_ownership(): void
    {
        [$session, $student] = $this->scenario('online');
        [$coordinator, $device, $header] = $this->terminal();
        [, , $otherHeader] = $this->terminal();
        $this->actingAs($student)->get('/admin/physical-terminals')->assertForbidden();
        $teacher = User::factory()->teacher()->create();
        $this->withSession(['password_hash_web' => $teacher->password])->actingAs($teacher);
        $this->get('/sessions/'.$session->id.'/physical-terminal')->assertForbidden();
        $this->withSession(['password_hash_web' => $coordinator->password])->actingAs($coordinator);
        $this->get('/admin/physical-terminals')->assertOk()->assertDontSee($device['secret']);
        $this->get('/admin/nfc-credentials/terminal-jobs')->assertOk()->assertSee('Preparar etiqueta');
        $this->post('/admin/nfc-credentials/terminal-jobs', ['terminal_id' => $device['id'], 'student_id' => $student->id])->assertRedirect();
        $claim = $this->postJson('/api/v1/terminal/commands/claim', [], $header)->assertOk()->json('command');
        $this->postJson('/api/v1/terminal/commands/claim', [], $otherHeader)->assertOk()->assertJsonPath('command', null);
        $route = '/api/v1/terminal/commands/'.$claim['id'].'/confirm-write';
        $readback = ['lease_id' => $claim['lease_id'], 'readback' => $claim['credential_token']];
        $this->postJson($route, $readback, $otherHeader)->assertNotFound();
        $this->delete('/admin/nfc-credentials/terminal-jobs/'.$claim['id'], ['reason' => 'Cancelar la escritura en revisión.'])->assertRedirect();
        $this->postJson($route, $readback, $header)->assertConflict();
        $this->assertDatabaseHas('terminal_commands', ['id' => $claim['id'], 'state' => 'cancelled', 'delivery' => null]);
        $this->post('/admin/physical-terminals/'.$device['id'].'/rotate', ['reason' => 'Actualizar acceso de prueba.'])->assertOk()->assertDontSee($device['secret']);
        $this->getJson('/api/v1/terminal/state', $header)->assertUnauthorized();
    }

    public function test_device_write_requires_matching_readback_and_nfc_shares_the_existing_result(): void
    {
        [$session, $student, $portal] = $this->scenario('online');
        [$coordinator, $device, $header] = $this->terminal();
        $workflow = app(PhysicalTerminalWorkflow::class);
        $credentials = app(CredentialManager::class);
        $old = $credentials->issue($coordinator, $student);
        $credentials->confirm($coordinator, $old['credential'], $old['token']);
        $id = $workflow->issue($coordinator, $student, $device['id']);

        $claim = $this->postJson('/api/v1/terminal/commands/claim', [], $header)->assertOk()->json('command');
        $this->postJson('/api/v1/terminal/commands/claim', [], $header)->assertOk()->assertJsonPath('command.lease_id', $claim['lease_id']);
        $this->assertNotSame($claim['credential_token'], DB::table('terminal_commands')->where('id', $id)->value('delivery'));
        $confirm = '/api/v1/terminal/commands/'.$id.'/confirm-write';
        $this->postJson($confirm, ['lease_id' => $claim['lease_id'], 'readback' => $old['token']], $header)->assertUnprocessable();
        $this->assertSame('active', $old['credential']->fresh()->state);

        $readback = ['lease_id' => $claim['lease_id'], 'readback' => $claim['credential_token']];
        $this->postJson($confirm, $readback, $header)->assertOk()->assertJsonPath('status', 'confirmed');
        $this->postJson($confirm, $readback, $header)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame('revoked', $old['credential']->fresh()->state);
        $workflow->assign($session->offering->teacher, $session, ['terminal_id' => $device['id'], 'portal_id' => $portal['portal']->id, 'methods' => ['nfc', 'qr']]);
        $assignment = $this->getJson('/api/v1/terminal/state', $header)->assertOk()->json('assignment.id');
        $packet = ['event_id' => (string) Str::uuid(), 'assignment_id' => $assignment, 'credential_token' => $claim['credential_token']];
        $this->postJson('/api/v1/terminal/nfc-readings', $packet, $header)->assertCreated()->assertExactJson(['status' => 'recorded']);
        $this->postJson('/api/v1/terminal/nfc-readings', $packet, $header)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseHas('terminal_attendance_events', ['event_id' => $packet['event_id'], 'assignment_id' => $assignment]);
        $this->actingAs($student)->postJson('/check-in/'.$portal['portal']->id, [
            'event_id' => (string) Str::uuid(), 'portal_token' => $portal['token'], 'method' => 'qr',
        ])->assertOk()->assertJsonPath('status', 'already_recorded');
        $this->assertDatabaseCount('attendances', 1);
        $workflow->disable($coordinator, $device['id'], 'Prueba de revocación.');
        $this->postJson('/api/v1/terminal/nfc-readings', $packet, $header)->assertUnauthorized();
    }

    public function test_shared_challenge_uses_browser_identity_geography_and_replay_after_expiry(): void
    {
        [$session, $student, $portal] = $this->scenario('in_person');
        [, $device, $header] = $this->terminal();
        $other = User::factory()->create();
        Enrollment::factory()->create(['offering_id' => $session->offering_id, 'student_id' => $other->id]);
        app(PhysicalTerminalWorkflow::class)->assign($session->offering->teacher, $session, [
            'terminal_id' => $device['id'], 'portal_id' => $portal['portal']->id, 'methods' => ['qr'],
        ]);
        $assignment = $this->getJson('/api/v1/terminal/state', $header)->assertOk()->assertJsonPath('assignment.challenge', null)->json('assignment.id');
        $qr = $this->postJson('/api/v1/terminal/qr-challenges', ['assignment_id' => $assignment], $header)->assertOk()->assertJsonPath('qr.total_pixels', 58)->json();
        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{20}\z/', $qr['token']);
        $this->postJson('/api/v1/terminal/qr-challenges', ['assignment_id' => $assignment], $header)->assertOk()->assertJsonPath('challenge_id', $qr['challenge_id']);
        $this->actingAs($student)->get('/student/terminal-qr')->assertOk()->assertHeader('Permissions-Policy', 'nfc=(self), camera=(self), microphone=(), geolocation=(self)');
        $this->get('/check-in/'.$portal['portal']->id.'?method=qr')->assertOk()->assertHeader('Permissions-Policy', 'nfc=(self), camera=(), microphone=(), geolocation=(self)');
        $this->postJson('/student/terminal-qr/resolve', ['short_token' => $qr['token']])->assertOk()->assertJsonPath('requires_location', true);

        $data = ['event_id' => (string) Str::uuid(), 'challenge_id' => $qr['challenge_id'], 'portal_id' => $portal['portal']->id, 'short_token' => $qr['token']];
        $this->postJson('/student/terminal-qr/check-in', [...$data, 'event_id' => (string) Str::uuid()])->assertUnprocessable();
        $data['location'] = [...$this->location(), 'portal_id' => $portal['portal']->id];
        $this->postJson('/student/terminal-qr/check-in', [...$data, 'student_id' => $other->id])->assertCreated()->assertJsonPath('student_id', $student->id);
        $this->withSession(['password_hash_web' => $other->password])->actingAs($other)
            ->postJson('/student/terminal-qr/check-in', [...$data, 'event_id' => (string) Str::uuid()])->assertCreated()->assertJsonPath('student_id', $other->id);

        $this->travel(91)->seconds();
        $this->artisan('terminal:prune-deliveries')->assertSuccessful();
        $this->withSession(['password_hash_web' => $student->password])->actingAs($student);
        $this->postJson('/student/terminal-qr/check-in', $data)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/student/terminal-qr/check-in', [...$data, 'event_id' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'terminal_qr_expired');
        $this->assertDatabaseCount('attendances', 2);
    }

    private function scenario(string $modality): array
    {
        $this->travelTo(now()->setDateTime(2026, 10, 13, 14, 10, 0));
        $session = ClassSession::factory()->create([
            'starts_at' => '2026-10-13T14:00:00Z', 'ends_at' => '2026-10-13T15:00:00Z',
            'state' => 'in_progress', 'modality' => $modality,
        ]);
        $student = Enrollment::factory()->create(['offering_id' => $session->offering_id])->student;
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session, ['nfc', 'qr'], 15, $this->location());

        return [$session, $student, $portal];
    }

    private function terminal(): array
    {
        $admin = User::factory()->coordinator()->create();
        $device = app(PhysicalTerminalWorkflow::class)->provision($admin, ['name' => 'Terminal de prueba', 'capabilities' => ['nfc', 'write', 'qr']]);
        $header = ['Authorization' => 'Bearer '.$device['secret']];
        $this->postJson('/api/v1/terminal/heartbeat', [], $header)->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        return [$admin, $device, $header];
    }

    private function location(): array
    {
        return ['latitude' => 13.7, 'longitude' => -89.2, 'accuracy' => 1, 'timestamp' => now()->getTimestampMs()];
    }
}
