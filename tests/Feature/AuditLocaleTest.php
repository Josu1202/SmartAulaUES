<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class AuditLocaleTest extends TestCase
{
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
