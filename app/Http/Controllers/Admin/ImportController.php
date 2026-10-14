<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\CsvImporter;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\User;
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
        $preview = $request->session()->get('academic_import');
        if ($preview && ($preview['owner_id'] !== $request->user()->id || $preview['expires_at'] < now()->timestamp)) {
            $request->session()->forget('academic_import');
            $preview = null;
        }
        $review = $preview ? $importer->review($preview['kind'], $preview['rows'], $preview['offering_id'] ? Offering::with('cycle')->find($preview['offering_id']) : null) : null;
        $offerings = Offering::with('cycle', 'subject')->whereHas('cycle', fn ($q) => $q->where('status', '!=', 'closed'))->orderBy('id')->get();

        return view('admin.imports.index', compact('preview', 'review', 'offerings'));
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
        $request->session()->forget('academic_import');
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(CsvImporter::HEADERS))],
            'file' => ['required', 'file', 'max:1024', 'extensions:csv,txt'],
            'offering_id' => [Rule::requiredIf($request->input('kind') === 'enrollments'), 'nullable', 'integer', 'exists:offerings,id'],
            'initial_password' => [Rule::requiredIf($request->input('kind') === 'users'), 'nullable', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);
        $rows = $importer->read($data['file'], $data['kind']);
        $request->session()->put('academic_import', [
            'owner_id' => $request->user()->id, 'kind' => $data['kind'], 'rows' => $rows,
            'offering_id' => $data['kind'] === 'enrollments' ? (int) $data['offering_id'] : null,
            'password_hash' => $data['kind'] === 'users' ? Hash::make($data['initial_password']) : null,
            'nonce' => Str::random(40), 'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return to_route('admin.imports.index');
    }

    public function confirm(Request $request, CsvImporter $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $request->validate(['nonce' => ['required', 'string', 'size:40']]);
        $preview = $request->session()->get('academic_import');
        if (! $preview || $preview['owner_id'] !== $request->user()->id || $preview['expires_at'] < now()->timestamp || ! hash_equals($preview['nonce'], $request->input('nonce'))) {
            throw ValidationException::withMessages(['file' => 'La revisión expiró o ya fue confirmada. Vuelve a cargar el CSV.']);
        }
        $count = $importer->confirm($request->user(), $preview);
        $request->session()->forget('academic_import');

        return to_route('admin.imports.index')->with('status', $count.' '.($count === 1 ? 'registro importado' : 'registros importados').' correctamente.');
    }
}
