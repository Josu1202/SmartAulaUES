<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Jobs\GenerateReportPdf;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPdfMultipageTest extends TestCase
{
    public function test_pdf_with_ninety_students_has_multiple_pages_and_valid_integrity(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 12, 0, 0));

        $period = AssessmentPeriod::factory()->create([
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-22',
        ]);

        $period->offering->update(['capacity' => 120]);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);

        Enrollment::factory()->count(89)->create([
            'offering_id' => $period->offering_id,
        ]);

        $student = User::factory()->create([
            'name' => 'José Núñez Álvarez',
        ]);

        Enrollment::factory()->create([
            'offering_id' => $period->offering_id,
            'student_id' => $student->id,
        ]);

        $coordinator = User::factory()->coordinator()->create();

        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);

        $report = ReportVersion::findOrFail(
            $period->fresh()->current_report_id
        );

        $this->assertCount(90, $report->snapshot['rows']);

        Storage::fake('private');

        $exporter = app(VersionedPdfExporter::class);
        $teacher = $period->offering->teacher;

        (new GenerateReportPdf($report->id, $teacher->id))
            ->handle($exporter);

        $report->refresh();

        $this->assertTrue($exporter->fileIsValid($report));

        $content = Storage::disk('private')->get($report->pdf_path);

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertSame(
            hash('sha256', $content),
            $report->file_hash
        );

        // Dompdf identifica cada página mediante un objeto /Type /Page.
        $pages = preg_match_all('/\/Type\s*\/Page\b/', $content);

        $this->assertGreaterThanOrEqual(
            2,
            $pages,
            'El PDF de 90 estudiantes debería ocupar varias páginas.'
        );

        // Evidencia temporal para la inspección visual posterior.
        $saved = file_put_contents(
            '/tmp/SmartAulaUES_B02_5_PDF_multipagina_prueba.pdf',
            $content
        );

        $this->assertSame(strlen($content), $saved);
    }
}
