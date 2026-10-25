<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\CsvImporter;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\User;
use App\Rules\PasswordByteLimit;
use App\Rules\StudentLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request, CsvImporter $importer): View
    {
        Gate::authorize('create', User::class);
        $data = $request->validate(['preview' => ['nullable', 'string', 'size:40']]);
        $id = $data['preview'] ?? $request->session()->get('academic_import_id', '');
        $preview = $request->session()->get('academic_imports.'.$id);
        $previewMessage = null;
        if ($preview && ($preview['owner_id'] !== $request->user()->id || $preview['expires_at'] <= now()->timestamp)) {
            // Reviews in other tabs remain independently addressable.
            $preview = null;
        }
        if ($request->filled('preview') && ! $preview) {
            $previewMessage = 'La revisión expiró, ya fue confirmada o no pertenece a esta sesión. Carga el archivo nuevamente.';
        }
        $review = $preview ? $importer->review($preview['kind'], $preview['rows'], $preview['offering_id'] ? Offering::with('cycle')->find($preview['offering_id']) : null) : null;
        $offerings = Offering::where('active', true)->with('cycle', 'subject')->whereHas('cycle', fn ($q) => $q->where('status', '!=', 'closed'))->orderBy('id')->get();

        return view('admin.imports.index', compact('preview', 'review', 'offerings', 'previewMessage'));
    }

    public function template(string $kind): StreamedResponse
    {
        Gate::authorize('create', User::class);
        abort_unless(isset(CsvImporter::HEADERS[$kind]), 404);

        return response()->streamDownload(function () use ($kind): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, CsvImporter::HEADERS[$kind], ',', '"', '');
            fclose($stream);
        }, 'smartaulaues-'.$kind.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, CsvImporter $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);
        // Reviews in other tabs remain independently addressable.
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(CsvImporter::HEADERS))],
            'file' => ['required', 'file', 'max:1024', 'extensions:csv,txt'],
            'offering_id' => [Rule::requiredIf($request->input('kind') === 'enrollments'), 'nullable', 'integer', 'exists:offerings,id'],
            'initial_password' => [Rule::requiredIf(in_array($request->input('kind'), ['users', 'students', 'teachers'])), 'nullable', 'string', 'min:12', 'max:128', new PasswordByteLimit, 'confirmed'],
        ]);
        $rows = $importer->read($data['file'], $data['kind']);
        $preview = [
            'owner_id' => $request->user()->id, 'kind' => $data['kind'], 'rows' => $rows,
            'offering_id' => $data['kind'] === 'enrollments' ? (int) $data['offering_id'] : null,
            'password_hash' => in_array($data['kind'], ['users', 'students', 'teachers']) ? Hash::make($data['initial_password']) : null,
            'nonce' => Str::random(40), 'expires_at' => now()->addMinutes(15)->timestamp,
        ];

        return $this->storePreview($request, $preview, $importer);
    }

    public function confirm(Request $request, CsvImporter $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $request->validate(['nonce' => ['required', 'string', 'size:40']]);
        $preview = $request->session()->get('academic_imports.'.$request->input('nonce'));
        if (! $preview || $preview['owner_id'] !== $request->user()->id || $preview['expires_at'] <= now()->timestamp || ! hash_equals($preview['nonce'], $request->input('nonce'))) {
            throw ValidationException::withMessages(['file' => 'La revisión expiró o ya fue confirmada. Vuelve a cargar el CSV.']);
        }
        $count = $importer->confirm($request->user(), $preview);
        $request->session()->forget('academic_imports.'.$preview['nonce']);
        if ($request->session()->get('academic_import_id') === $preview['nonce']) {
            $request->session()->forget('academic_import_id');
        }

        return to_route('admin.imports.index')->with('status', $count.' '.($count === 1 ? 'registro importado' : 'registros importados').' correctamente.');
    }

    public function manualForm(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.batch');
    }

    public function manualPreview(Request $request, CsvImporter $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);
        // Reviews in other tabs remain independently addressable.
        $data = $request->validate(['entry_year' => ['required', 'integer', 'between:1900,2200'], 'study_year' => ['required', new StudentLevel], 'students' => ['required', 'array', 'min:1', 'max:100'], 'students.*.name' => ['required', 'string', 'max:255'], 'students.*.email' => ['required', 'email:rfc', 'max:255'], 'students.*.student_code' => ['required', 'string', 'max:30'], 'initial_password' => ['required', 'string', 'min:12', 'max:128', new PasswordByteLimit, 'confirmed']]);
        $rows = [];
        foreach (array_values($data['students']) as $i => $student) {
            $rows[] = ['line' => $i + 2, 'fields' => ['name' => trim($student['name']), 'email' => trim($student['email']), 'student_code' => trim($student['student_code']), 'entry_year' => (string) $data['entry_year'], 'study_year' => (string) $data['study_year']]];
        }
        $preview = ['owner_id' => $request->user()->id, 'kind' => 'students', 'rows' => $rows, 'offering_id' => null, 'password_hash' => Hash::make($data['initial_password']), 'nonce' => Str::random(40), 'expires_at' => now()->addMinutes(15)->timestamp];

        return $this->storePreview($request, $preview, $importer);
    }

    private function storePreview(Request $request, array $preview, CsvImporter $importer): RedirectResponse
    {
        $reviews = collect($request->session()->get('academic_imports', []))->filter(fn ($p) => $p['expires_at'] > now()->timestamp && $p['owner_id'] === $request->user()->id)->all();
        if (count($reviews) >= 10) {
            throw ValidationException::withMessages(['file' => 'Hay diez revisiones pendientes. Confirma una o espera su vencimiento.']);
        }
        $preview['baseline'] = $importer->baseline($preview);
        $reviews[$preview['nonce']] = $preview;
        $request->session()->put('academic_imports', $reviews);
        $request->session()->put('academic_import_id', $preview['nonce']);

        return to_route('admin.imports.index', ['preview' => $preview['nonce']]);
    }
}
