<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('plantilla.csv', $content);
    }

    private function previewUsers(string $content): void
    {
        $this->post('/admin/imports/preview', ['kind' => 'users', 'file' => $this->csv($content), 'initial_password' => 'Csv-password-2026!', 'initial_password_confirmation' => 'Csv-password-2026!'])->assertSessionHasNoErrors()->assertRedirect('/admin/imports');
    }

    public function test_preview_does_not_write_and_confirmation_creates_the_entire_batch(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\n\"Nombre, compuesto\",student@example.test,student,ST001\nDocente,teacher@example.test,teacher,\n");
        $this->assertDatabaseCount('users', 1);
        $preview = session('academic_import');
        $this->assertStringNotContainsString('Csv-password-2026!', json_encode($preview));
        $this->assertTrue(Hash::check('Csv-password-2026!', $preview['password_hash']));
        $this->get('/admin/imports')->assertOk()->assertSee('Nombre, compuesto')->assertSee('Listo para confirmar');
        $this->post('/admin/imports/confirm', ['nonce' => $preview['nonce']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertTrue(Hash::check('Csv-password-2026!', User::where('student_code', 'ST001')->sole()->password));
        $this->assertNull(session('academic_import'));
        $this->post('/admin/imports/confirm', ['nonce' => $preview['nonce']])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('users', 3);
    }

    public function test_wrong_headers_are_rejected_before_preview(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/imports/preview', ['kind' => 'users', 'file' => $this->csv("email,name,role,student_code\nx@example.test,Nombre,student,X001\n"), 'initial_password' => 'Csv-password-2026!', 'initial_password_confirmation' => 'Csv-password-2026!'])->assertSessionHasErrors('file');
        $this->assertNull(session('academic_import'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_rows_cannot_be_confirmed_even_with_a_forged_submit(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\nUno,one@example.test,student,D001\nDos,ONE@example.test,student,D001\n");
        $this->get('/admin/imports')->assertOk()->assertSee('Requiere correcciones')->assertSee('Correo duplicado');
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_existing_and_coordinator_accounts_cannot_be_imported_or_overwritten(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $hash = $existing->password;
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\nOtro,existing@example.test,student,X001\nAdmin,new@example.test,coordinator,\n");
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertSame($hash, $existing->fresh()->password);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_conflict_added_after_preview_rejects_all_rows(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\nPrimero,first@example.test,student,F001\nSegundo,second@example.test,student,S001\n");
        User::factory()->create(['email' => 'second@example.test', 'student_code' => 'OTHER001']);
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['email' => 'first@example.test']);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_enrollment_batch_respects_capacity_and_cycle_dates(): void
    {
        $this->travelTo(now(config('smartaulaues.display_timezone'))->setDate(2026, 10, 13)->setTime(12, 0));
        $offering = Offering::factory()->create(['capacity' => 1]);
        User::factory()->create(['student_code' => 'ST001']);
        User::factory()->create(['student_code' => 'ST002']);
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/imports/preview', ['kind' => 'enrollments', 'offering_id' => $offering->id, 'file' => $this->csv("student_code,active_from,active_to\nST001,2026-01-01,\nST002,2026-01-01,\n")])->assertSessionHasNoErrors();
        $this->get('/admin/imports')->assertOk()->assertSee('supera el cupo');
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('enrollments', 0);
        $this->post('/admin/imports/preview', ['kind' => 'enrollments', 'offering_id' => $offering->id, 'file' => $this->csv("student_code,active_from,active_to\nST001,2025-12-01,\n")])->assertSessionHasNoErrors();
        $this->get('/admin/imports')->assertSee('dentro de las fechas del ciclo');
    }

    public function test_valid_enrollment_batch_uses_the_selected_group(): void
    {
        $offering = Offering::factory()->create();
        $student = User::factory()->create(['student_code' => 'ST001']);
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/imports/preview', ['kind' => 'enrollments', 'offering_id' => $offering->id, 'file' => $this->csv("student_code,active_from,active_to\nst001,2026-01-01,\n")])->assertSessionHasNoErrors();
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollments', ['offering_id' => $offering->id, 'student_id' => $student->id]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_capacity_changed_after_preview_rejects_the_whole_batch(): void
    {
        $this->travelTo(now(config('smartaulaues.display_timezone'))->setDate(2026, 10, 13)->setTime(12, 0));
        $offering = Offering::factory()->create(['capacity' => 1]);
        User::factory()->create(['student_code' => 'ST001']);
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/imports/preview', ['kind' => 'enrollments', 'offering_id' => $offering->id, 'file' => $this->csv("student_code,active_from,active_to\nST001,2026-01-01,\n")])->assertSessionHasNoErrors();
        Enrollment::factory()->create(['offering_id' => $offering->id]);
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import.nonce')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_expired_and_foreign_preview_cannot_be_confirmed(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\nNuevo,new@example.test,student,NEW001\n");
        $nonce = session('academic_import.nonce');
        $this->post('/admin/imports/confirm', ['nonce' => str_repeat('x', 40)])->assertSessionHasErrors('file');
        $this->travel(16)->minutes();
        $this->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_templates_are_downloadable_and_unknown_kind_is_rejected(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $response = $this->get('/admin/imports/templates/users')->assertOk()->assertDownload('smartaulaues-users.csv');
        $this->assertSame("name,email,role,student_code\n", $response->streamedContent());
        $this->get('/admin/imports/templates/unknown')->assertNotFound();
    }

    public function test_invalid_csv_cell_is_escaped_in_the_preview(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->previewUsers("name,email,role,student_code\n<script>alert(1)</script>,new@example.test,student,NEW001\n");
        $this->get('/admin/imports')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
