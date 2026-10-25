<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserMessagesTest extends TestCase
{
    public function test_spanish_catalogue_covers_every_framework_validation_rule_and_nested_type(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        unset($english['custom'], $english['attributes']);
        $spanish = require lang_path('es/validation.php');
        foreach (array_keys(Arr::dot($english)) as $key) {
            $this->assertTrue(Arr::has($spanish, $key), 'Missing translation: '.$key);
            $this->assertNotSame('validation.'.$key, trans('validation.'.$key, locale: 'es'));
        }
        foreach (['auth', 'pagination', 'passwords'] as $catalogue) {
            $defaults = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/'.$catalogue.'.php');
            foreach (array_keys($defaults) as $key) {
                $this->assertNotSame($catalogue.'.'.$key, trans($catalogue.'.'.$key, locale: 'es'));
            }
        }
        $data = ['starts_on' => 'not-a-date', 'ends_on' => '2026-99-99', 'initial_password' => 'short', 'student_ids' => 'wrong', 'students' => [['email' => 'invalid']]];
        $errors = Validator::make($data, ['starts_on' => 'date_format:Y-m-d', 'ends_on' => 'date', 'initial_password' => 'min:12', 'student_ids' => 'array', 'students.*.email' => 'email'])->errors()->all();
        $text = implode(' ', $errors);
        $this->assertStringContainsString('año-mes-día', $text);
        foreach (['validation.', 'starts_on', 'ends_on', 'initial_password', 'student_ids', 'students.0.email', 'must be', 'Y-m-d'] as $internal) {
            $this->assertStringNotContainsString($internal, $text);
        }
    }

    public function test_real_academic_forms_return_cause_specific_spanish_errors(): void
    {
        $admin = User::factory()->coordinator()->create();
        $this->actingAs($admin)->withSession(['password_hash_web' => $admin->password]);
        $response = $this->postJson('/admin/cycles', ['code' => 'Ciclo académico', 'starts_on' => 'bad-date', 'ends_on' => '2026-10-01', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->assertStringContainsString('año-mes-día', $response->json('errors.starts_on.0'));
        $response = $this->postJson('/admin/cycles', ['code' => 'Ciclo académico', 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-01', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->assertStringContainsString('fecha inicial', $response->json('errors.ends_on.0'));
        $period = AssessmentPeriod::factory()->create();
        $this->postJson('/offerings/'.$period->offering_id.'/sessions', ['period_id' => $period->id, 'starts_at' => 'incorrect', 'ends_at' => 'incorrect', 'modality' => 'online'])->assertUnprocessable()->assertJsonValidationErrors(['starts_at', 'ends_at'])->assertJsonPath('message', 'Revisa los datos indicados.');
        $this->postJson('/admin/nfc-credentials', ['student_id' => 'incorrect'])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $password = str_repeat('ñ', 37);
        $response = $this->postJson('/admin/imports/preview', ['kind' => 'teachers', 'file' => UploadedFile::fake()->createWithContent('docentes.csv', "name,email\nAna,ana@example.test\n"), 'initial_password' => $password, 'initial_password_confirmation' => $password])->assertUnprocessable();
        $this->assertContains('La contraseña es demasiado larga. Usa una más corta.', $response->json('errors.initial_password'));
        foreach (['validation.', 'initial_password', 'bytes', 'SQLSTATE'] as $internal) {
            $this->assertStringNotContainsString($internal, implode(' ', $response->json('errors.initial_password')));
        }
    }

    public static function httpStatuses(): array
    {
        return [[403, 'permiso'], [404, 'No encontramos'], [419, 'sesión terminó'], [429, 'Espera un minuto'], [503, 'no está disponible']];
    }

    #[DataProvider('httpStatuses')]
    public function test_html_and_json_http_errors_do_not_expose_framework_text(int $status, string $expected): void
    {
        config(['app.debug' => true]);
        Route::get('/message-probe', fn () => abort($status, 'INTERNAL_FRAMEWORK_MESSAGE'));
        $this->get('/message-probe')->assertStatus($status)->assertSee($expected)->assertDontSee('INTERNAL_FRAMEWORK_MESSAGE')->assertDontSee('Stack trace');
        $response = $this->getJson('/message-probe')->assertStatus($status);
        $this->assertStringContainsString($expected, $response->json('message'));
        $response->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
    }

    public function test_sql_errors_keep_safe_diagnostics_without_disclosing_bindings_or_request_secrets(): void
    {
        config(['app.debug' => true]);
        $previous = new PDOException('SQLSTATE[08006] secret-password=do-not-record');
        $previous->errorInfo = ['08006', 7, 'private SQL failure'];
        Route::get('/database-probe', fn () => throw new QueryException('pgsql', 'select * from users where password = ?', ['secret-bound-value'], $previous));
        Log::shouldReceive('error')->twice()->withArgs(function (string $message, array $context): bool {
            $text = json_encode([$message, $context]);
            $this->assertStringNotContainsString('secret-', $text);
            $this->assertStringNotContainsString('select *', $text);
            $this->assertSame('08006', $context['sqlstate']);
            $this->assertArrayHasKey('query_fingerprint', $context);
            $this->assertArrayHasKey('frames', $context);

            return true;
        });
        $this->get('/database-probe?password=secret-request')->assertServiceUnavailable()->assertSee('El servicio no está disponible')->assertDontSee('SQLSTATE')->assertDontSee('secret-');
        $this->getJson('/database-probe')->assertServiceUnavailable()->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
    }

    public function test_expired_json_authentication_has_a_spanish_message(): void
    {
        $this->getJson('/dashboard')->assertUnauthorized()->assertJsonPath('message', 'Tu sesión terminó. Inicia sesión nuevamente.');
    }
}
