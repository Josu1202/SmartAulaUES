<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class AuditLocaleTest extends TestCase
{
    public function test_expanded_audit_values_are_readable_without_changing_the_original_record_or_exposing_secrets(): void
    {
        $actor = User::factory()->coordinator()->create();
        $payload = ['entry_year' => 2026, 'must_change_password' => true, 'role' => 'student', 'starts_on' => '2026-10-24',
            'started_at' => '2026-10-25T04:00:00Z', 'enabled_methods' => ['link', 'nfc_tag'],
            'periods' => [['number' => 1, 'ends_on' => '2026-10-24']], 'password' => 'SECRET_NOT_PUBLIC', 'token_hash' => 'SECRET_NOT_PUBLIC'];
        $log = AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'User', 'entity_id' => (string) $actor->id, 'action' => 'user.updated', 'after' => $payload]);
        $stored = $log->fresh()->after;
        $this->actingAs($actor)->get('/admin/audit')->assertOk()->assertSee('Año de ingreso: 2026')->assertSee('Cambio de contraseña obligatorio: Sí')
            ->assertSee('Perfil: Estudiante')->assertSee('Fecha inicial: 24/10/2026')->assertSee('Inicio real: 24/10/2026 22:00:00')
            ->assertSee('Métodos de registro: Enlace; Etiqueta compartida (declarado)')->assertSee('Número de cómputo: 1')
            ->assertDontSee('entry_year')->assertDontSee('must_change_password')->assertDontSee('SECRET_NOT_PUBLIC')->assertDontSee('token_hash');
        $this->assertSame($stored, $log->fresh()->after);
    }

    public function test_audit_page_and_filters_work_when_the_active_locale_has_no_audit_translations(): void
    {
        $actor = User::factory()->coordinator()->create();
        $this->actingAs($actor);
        AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'User', 'entity_id' => '1', 'action' => 'user.created', 'reason' => 'Alta de cuenta de prueba']);
        AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'ClassSession', 'entity_id' => '2', 'action' => 'session.start', 'reason' => 'Inicio de clase de prueba']);

        config(['app.fallback_locale' => 'en']);
        app('translator')->setFallback('en');
        foreach (['es', 'en', 'zz'] as $locale) {
            app()->setLocale($locale);
            $this->get('/admin/audit')->assertOk()->assertSee('Cuenta creada')->assertSee('Clase iniciada')->assertSee('Sesión de clase');
            $this->get('/admin/audit?entity=ClassSession')->assertOk()->assertSee('Inicio de clase de prueba')->assertDontSee('Alta de cuenta de prueba')->assertSee('Clase iniciada');
        }
    }

    public function test_entity_validation_uses_stable_codes_and_rejects_translated_or_unknown_values(): void
    {
        app()->setLocale('en');
        app('translator')->setFallback('en');
        $this->actingAs(User::factory()->coordinator()->create());

        foreach (['User', 'AcademicCycle', 'CycleFormat', 'Subject', 'Offering', 'AssessmentPeriod', 'Enrollment', 'CsvImport', 'ClassSession', 'AttendancePortal', 'ReaderSession', 'NfcCredential', 'Attendance', 'AbsenceRequest'] as $entity) {
            $this->get('/admin/audit?entity='.$entity)->assertOk();
        }
        foreach (['Sesión de clase', 'UnknownEntity'] as $entity) {
            $this->getJson('/admin/audit?'.http_build_query(['entity' => $entity]))->assertUnprocessable()->assertJsonValidationErrors('entity');
        }
    }
}
