<?php

namespace Tests\Feature;

use App\Actions\Academic\CycleFormatManager;
use App\Models\AcademicCycle;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnnualOrganizationTest extends TestCase
{
    private function formatData(AcademicCycle $cycle): array
    {
        return ['cycle_id' => $cycle->id, 'name' => 'Calendario regular', 'active' => 1, 'periods' => [['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-30'], ['number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2026-08-31'], ['number' => 3, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31']]];
    }

    public function test_directories_are_separate_and_student_year_filters_apply(): void
    {
        $actor = User::factory()->coordinator()->create();
        User::factory()->teacher()->create(['name' => 'DOCENTE_EXCLUSIVO']);
        User::factory()->create(['name' => 'ESTUDIANTE_ANIO_DOS', 'entry_year' => 2025, 'study_year' => 2]);
        User::factory()->create(['name' => 'ESTUDIANTE_ANIO_UNO', 'entry_year' => 2026, 'study_year' => 1]);
        $this->actingAs($actor)->get('/admin/students?study_year=2&entry_year=2025')->assertOk()->assertSee('ESTUDIANTE_ANIO_DOS')->assertDontSee('DOCENTE_EXCLUSIVO')->assertDontSee('ESTUDIANTE_ANIO_UNO');
        $this->get('/admin/teachers')->assertOk()->assertSee('DOCENTE_EXCLUSIVO')->assertDontSee('ESTUDIANTE_ANIO_DOS');
        $this->get('/admin/users')->assertOk()->assertDontSee('DOCENTE_EXCLUSIVO')->assertDontSee('ESTUDIANTE_ANIO_DOS');
    }

    public function test_student_directory_requires_years_and_cannot_create_other_roles(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $data = ['name' => 'Nuevo', 'email' => 'new@example.test', 'role' => 'student', 'student_code' => 'NEW001', 'active' => 1, 'password' => 'Academic-password-2026!', 'password_confirmation' => 'Academic-password-2026!'];
        $this->post('/admin/students', $data)->assertSessionHasErrors(['entry_year', 'study_year']);
        $this->post('/admin/students', [...$data, 'role' => 'coordinator', 'entry_year' => 2026, 'study_year' => 1])->assertSessionHasErrors('role');
        $this->post('/admin/students', [...$data, 'entry_year' => 2026, 'study_year' => 1])->assertRedirect('/admin/students')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['student_code' => 'NEW001', 'entry_year' => 2026, 'study_year' => 1]);
    }

    public function test_legacy_students_have_unassigned_years_without_being_lost(): void
    {
        $user = User::factory()->create(['name' => 'ESTUDIANTE_ANTERIOR']);
        $this->actingAs(User::factory()->coordinator()->create())->get('/admin/students')->assertOk()->assertSee('ESTUDIANTE_ANTERIOR')->assertSee('Sin asignar');
        $this->assertNull($user->fresh()->entry_year);
    }

    public function test_manual_bulk_registration_uses_preview_and_atomic_confirmation(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/students/batch', ['entry_year' => 2026, 'study_year' => 2, 'students' => [['name' => 'Uno', 'email' => 'one@example.test', 'student_code' => 'AA001'], ['name' => 'Dos', 'email' => 'two@example.test', 'student_code' => 'AA002']], 'initial_password' => 'Bulk-password-2026!', 'initial_password_confirmation' => 'Bulk-password-2026!'])->assertRedirect('/admin/imports')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 1);
        $this->get('/admin/imports')->assertOk()->assertSee('AA001');
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['student_code' => 'AA002', 'entry_year' => 2026, 'study_year' => 2]);
    }

    public function test_student_csv_preserves_years_and_teacher_csv_is_separate(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        foreach (['students' => "name,email,student_code,entry_year,study_year\nPersona,student@example.test,ST001,2024,3\n", 'teachers' => "name,email\nDocente,teacher@example.test\n"] as $kind => $csv) {
            $this->post('/admin/imports/preview', ['kind' => $kind, 'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv), 'initial_password' => 'Csv-password-2026!', 'initial_password_confirmation' => 'Csv-password-2026!'])->assertSessionHasNoErrors();
            $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('users', ['student_code' => 'ST001', 'study_year' => 3, 'entry_year' => 2024]);
        $this->assertDatabaseHas('users', ['email' => 'teacher@example.test', 'role' => 'teacher', 'entry_year' => null]);
    }

    public function test_year_update_csv_preserves_existing_accounts_passwords_and_enrollments(): void
    {
        $student = User::factory()->create(['student_code' => 'PREV001', 'active' => false]);
        $password = $student->password;
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $this->actingAs(User::factory()->coordinator()->create())->post('/admin/imports/preview', ['kind' => 'student_years', 'file' => UploadedFile::fake()->createWithContent('years.csv', "student_code,entry_year,study_year\nPREV001,2023,4\n")])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->study_year);
        $this->get('/admin/imports')->assertOk()->assertSee('Años de estudiantes existentes');
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasNoErrors();
        $this->assertSame($password, $student->fresh()->password);
        $this->assertFalse($student->fresh()->active);
        $this->assertSame(4, $student->fresh()->study_year);
        $this->assertSame(2023, $student->fresh()->entry_year);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.years_updated']);
    }

    public function test_year_updates_reject_invalid_or_changed_batches_without_partial_writes(): void
    {
        $a = User::factory()->create(['student_code' => 'YEAR001']);
        $b = User::factory()->create(['student_code' => 'YEAR002']);
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/imports/preview', ['kind' => 'student_years', 'file' => UploadedFile::fake()->createWithContent('years.csv', "student_code,entry_year,study_year\nYEAR001,2025,2\nMISSING,2024,3\n")])->assertSessionHasNoErrors();
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertNull($a->fresh()->study_year);
        $this->post('/admin/imports/preview', ['kind' => 'student_years', 'file' => UploadedFile::fake()->createWithContent('years.csv', "student_code,entry_year,study_year\nYEAR001,2025,2\nYEAR002,2024,3\n")])->assertSessionHasNoErrors();
        $b->update(['role' => 'teacher', 'student_code' => null]);
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertNull($a->fresh()->study_year);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_format_applies_all_periods_automatically_and_freezes_used_dates(): void
    {
        $offering = Offering::factory()->create();
        $actor = User::factory()->coordinator()->create();
        $format = app(CycleFormatManager::class)->save($actor, $this->formatData($offering->cycle));
        $this->actingAs($actor)->post('/admin/offerings', [...array_map('strval', $offering->only(['cycle_id', 'subject_id', 'teacher_id', 'capacity'])), 'group_code' => '02', 'cycle_format_id' => (string) $format->id])->assertSessionHasNoErrors();
        $group = Offering::where('group_code', '02')->sole();
        $this->assertSame(3, $group->periods()->count());
        $this->assertSame($format->id, $group->cycle_format_id);
        $this->assertNotNull($group->periods()->first()->format_period_id);
        $this->put('/admin/formats/'.$format->id, $this->formatData($offering->cycle))->assertSessionHasErrors('periods');
        $p = $group->periods()->first();
        $this->put('/admin/offerings/'.$group->id.'/periods/'.$p->id, ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31', 'reason' => 'Cambiar calendario'])->assertSessionHasErrors('number');
        $this->assertSame('2026-04-30', $p->fresh()->ends_on->toDateString());
    }

    public function test_formats_reject_overlap_and_wrong_cycle_at_group_creation(): void
    {
        $offering = Offering::factory()->create();
        $actor = User::factory()->coordinator()->create();
        $data = $this->formatData($offering->cycle);
        $bad = $data;
        $bad['periods'][1]['starts_on'] = '2026-04-30';
        $this->actingAs($actor)->post('/admin/formats', $bad)->assertSessionHasErrors('periods');
        $format = app(CycleFormatManager::class)->save($actor, $data);
        $other = AcademicCycle::factory()->create();
        $this->post('/admin/offerings', [...$offering->only(['subject_id', 'teacher_id', 'capacity']), 'cycle_id' => $other->id, 'group_code' => 'BAD', 'cycle_format_id' => $format->id])->assertSessionHasErrors('cycle_format_id');
        $this->assertDatabaseMissing('offerings', ['group_code' => 'BAD']);
    }

    public function test_batch_enrollment_filters_students_and_rolls_back_if_capacity_is_exceeded(): void
    {
        $offering = Offering::factory()->create(['capacity' => 1]);
        $a = User::factory()->create(['name' => 'COHORTE_ELEGIDA', 'study_year' => 2, 'entry_year' => 2025]);
        $b = User::factory()->create(['name' => 'OTRA_COHORTE', 'study_year' => 1, 'entry_year' => 2026]);
        $this->actingAs(User::factory()->coordinator()->create())->get('/admin/offerings/'.$offering->id.'/students?study_year=2')->assertOk()->assertSee('COHORTE_ELEGIDA')->assertDontSee('OTRA_COHORTE');
        $this->post('/admin/offerings/'.$offering->id.'/students', ['student_ids' => [$a->id, $b->id], 'active_from' => '2026-01-01'])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_teacher_cannot_manage_students_or_cycle_formats(): void
    {
        $this->actingAs(User::factory()->teacher()->create());
        foreach (['/admin/students', '/admin/teachers', '/admin/formats/create', '/admin/students/batch'] as $url) {
            $this->get($url)->assertForbidden();
        } $this->post('/admin/formats', [])->assertForbidden();
        $this->post('/admin/students/batch', [])->assertForbidden();
    }
}
