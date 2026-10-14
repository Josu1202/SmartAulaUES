<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NfcCredential;
use App\Models\User;
use Tests\TestCase;

class CredentialWorkflowTest extends TestCase
{
    public function test_pending_credential_is_activated_only_with_its_token_and_replaces_previous(): void
    {
        $student = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $first = $this->postJson('/admin/nfc-credentials', ['student_id' => $student->id])->assertCreated()->json();
        $this->assertDatabaseHas('nfc_credentials', ['id' => $first['credential_id'], 'state' => 'pending']);
        $this->assertStringNotContainsString($first['token'], NfcCredential::sole()->toJson());
        $this->postJson('/admin/nfc-credentials/'.$first['credential_id'].'/confirm', ['credential_token' => 'SIAA:C1:'.str_repeat('A', 43)])->assertUnprocessable();
        $this->postJson('/admin/nfc-credentials/'.$first['credential_id'].'/confirm', ['credential_token' => $first['token']])->assertOk();
        $second = $this->postJson('/admin/nfc-credentials', ['student_id' => $student->id])->assertCreated()->json();
        $this->postJson('/admin/nfc-credentials/'.$second['credential_id'].'/confirm', ['credential_token' => $second['token']])->assertOk();
        $this->assertSame(1, NfcCredential::where('state', 'active')->count());
        $this->assertDatabaseHas('nfc_credentials', ['id' => $first['credential_id'], 'state' => 'revoked']);
        $this->assertStringNotContainsString($first['token'], AuditLog::all()->toJson());
    }

    public function test_expired_pending_and_non_student_accounts_cannot_be_activated(): void
    {
        $student = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $result = $this->postJson('/admin/nfc-credentials', ['student_id' => $student->id])->assertCreated()->json();
        $this->travel(11)->minutes();
        $this->postJson('/admin/nfc-credentials/'.$result['credential_id'].'/confirm', ['credential_token' => $result['token']])->assertUnprocessable();
        $teacher = User::factory()->teacher()->create();
        $this->postJson('/admin/nfc-credentials', ['student_id' => $teacher->id])->assertUnprocessable();
    }

    public function test_teacher_and_student_cannot_issue_or_revoke_credentials(): void
    {
        foreach ([User::factory()->teacher()->create(), User::factory()->create()] as $actor) {
            $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor)->postJson('/admin/nfc-credentials', ['student_id' => $actor->id])->assertForbidden();
            $this->get('/admin/nfc-credentials')->assertForbidden();
        }
    }
}
