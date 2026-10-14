<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbsencePermissionTest extends TestCase
{
    private function scenario(): array
    {
        $session = ClassSession::factory()->create(['state' => 'completed']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        Attendance::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent', 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now()]);

        return [$session, $enrollment];
    }

    public function test_student_submits_private_evidence_and_teacher_resolves_to_justified(): void
    {
        Storage::fake('private');
        [$session,$enrollment] = $this->scenario();
        $file = UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\nexample\n%%EOF");
        $this->actingAs($enrollment->student)->post('/sessions/'.$session->id.'/request', ['reason' => 'Atención médica documentada', 'evidence' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $record = AbsenceRequest::sole();
        Storage::disk('private')->assertExists($record->evidence_path);
        $this->get('/requests/'.$record->id.'/evidence')->assertOk()->assertDownload();
        $teacher = $session->offering->teacher;
        $this->withSession(['password_hash_web' => $teacher->password])->actingAs($teacher)->post('/requests/'.$record->id.'/resolve', ['decision' => 'approved', 'note' => 'Evidencia verificada por el docente'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('absence_requests', ['id' => $record->id, 'status' => 'approved']);
        $this->assertSame('justified', Attendance::sole()->status->value);
        $this->assertSame(1, $enrollment->student->notifications()->count());
        $this->post('/requests/'.$record->id.'/resolve', ['decision' => 'rejected', 'note' => 'Intento de segunda resolución'])->assertSessionHasErrors('decision');
    }

    public function test_other_students_and_unassigned_teachers_cannot_read_evidence(): void
    {
        Storage::fake('private');
        [$session,$enrollment] = $this->scenario();
        $record = AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'reason' => 'Motivo de prueba', 'evidence_path' => 'proof.pdf']);
        Storage::disk('private')->put('proof.pdf', 'Private');
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $other) {
            $this->withSession(['password_hash_web' => $other->password])->actingAs($other)->get('/requests/'.$record->id)->assertForbidden();
            $this->get('/requests/'.$record->id.'/evidence')->assertForbidden();
            $this->post('/requests/'.$record->id.'/resolve', ['decision' => 'approved', 'note' => 'Intento de otro perfil'])->assertForbidden();
        }
    }

    public function test_present_student_cannot_request_an_absence_and_invalid_upload_is_rejected(): void
    {
        Storage::fake('private');
        [$session,$enrollment] = $this->scenario();
        $this->actingAs($enrollment->student);
        $this->post('/sessions/'.$session->id.'/request', ['reason' => 'Motivo suficientemente largo', 'evidence' => UploadedFile::fake()->create('payload.html', 1, 'text/html')])->assertSessionHasErrors('evidence');
        $this->post('/sessions/'.$session->id.'/request', ['reason' => 'Motivo suficientemente largo', 'evidence' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('evidence');
        Attendance::sole()->update(['status' => 'present']);
        $this->post('/sessions/'.$session->id.'/request', ['reason' => 'Motivo suficientemente largo'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('absence_requests', 0);
    }

    public function test_pending_request_does_not_prevent_session_finalization_and_remains_visible(): void
    {
        [$session,$enrollment] = $this->scenario();
        $session->update(['state' => 'in_progress']);
        $request = AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'reason' => 'Solicitud pendiente para revisar']);
        $this->actingAs($session->offering->teacher)->post('/sessions/'.$session->id.'/state/finish', ['reason' => 'Finalización de la clase'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $request->fresh()->status);
        $this->get('/sessions/'.$session->id)->assertOk()->assertSee('Pendiente');
    }

    public function test_closed_period_prevents_requests_and_resolution(): void
    {
        [$session,$enrollment] = $this->scenario();
        $session->period->update(['state' => 'closed']);
        $this->actingAs($enrollment->student)->post('/sessions/'.$session->id.'/request', ['reason' => 'Solicitud fuera del cómputo abierto'])->assertSessionHasErrors('session');
        $this->assertDatabaseCount('absence_requests', 0);
    }

    public function test_notifications_can_only_be_marked_read_by_their_owner(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->notify(new AcademicNotice('Prueba', 'Solo el titular', '/dashboard'));
        $id = $a->notifications()->sole()->id;
        $this->actingAs($b)->post('/notifications/'.$id.'/read')->assertNotFound();
        $this->withSession(['password_hash_web' => $a->password])->actingAs($a)->post('/notifications/'.$id.'/read')->assertRedirect('/notifications');
        $this->assertNotNull($a->notifications()->sole()->read_at);
    }
}
