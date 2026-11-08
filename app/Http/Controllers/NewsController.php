<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AcademicCycle;
use App\Models\News;
use App\Models\Offering;
use App\Services\Communication\NewsPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class NewsController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', News::class);
        $filters = $request->validate(['cycle_id' => ['nullable', 'integer', 'min:1'], 'offering_id' => ['nullable', 'integer', 'min:1']]);
        $news = News::visibleTo($request->user())
            ->when($filters['cycle_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('cycle_id', $id)
                ->orWhereHas('offering', fn ($q) => $q->where('cycle_id', $id))))
            ->when($filters['offering_id'] ?? null, fn ($q, $id) => $q->where('offering_id', $id))
            ->with(['author', 'offering.subject', 'cycle'])->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(12)->withQueryString();

        return view('news.index', compact('news', 'filters'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', News::class);

        return $this->form($request, new News(['status' => 'draft']));
    }

    public function store(Request $request, NewsPublisher $publisher): RedirectResponse
    {
        Gate::authorize('create', News::class);
        $news = $publisher->create($request->user(), $this->validated($request));

        return to_route('news.show', $news)->with('status', 'Borrador creado. Publíquelo para hacerlo visible a la audiencia.');
    }

    public function show(News $news): View
    {
        Gate::authorize('view', $news);

        return view('news.show', ['news' => $news->load(['author', 'offering.subject', 'cycle'])]);
    }

    public function edit(Request $request, News $news): View
    {
        Gate::authorize('update', $news);

        return $this->form($request, $news);
    }

    public function update(Request $request, News $news, NewsPublisher $publisher): RedirectResponse
    {
        Gate::authorize('update', $news);
        $publisher->update($request->user(), $news, $this->validated($request));

        return to_route('news.show', $news)->with('status', 'Noticia actualizada y auditada.');
    }

    public function publish(Request $request, News $news, NewsPublisher $publisher): RedirectResponse
    {
        Gate::authorize('publish', $news);
        $publisher->publish($request->user(), $news);

        return to_route('news.show', $news)->with('status', 'Noticia publicada.');
    }

    public function archive(Request $request, News $news, NewsPublisher $publisher): RedirectResponse
    {
        Gate::authorize('archive', $news);
        $publisher->archive($request->user(), $news);

        return to_route('news.index')->with('status', 'Noticia archivada.');
    }

    private function form(Request $request, News $news): View
    {
        $actor = $request->user();
        $groups = Offering::visibleTo($actor)->with('subject')->orderByDesc('id')->get();
        $cycles = $actor->role === Role::Coordinator ? AcademicCycle::orderByDesc('starts_on')->get() : collect();

        return view('news.form', compact('news', 'groups', 'cycles'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:180'],
            'content' => ['required', 'string', 'min:5', 'max:10000'],
            'offering_id' => ['nullable', 'integer', 'exists:offerings,id'],
            'cycle_id' => ['nullable', 'integer', 'exists:academic_cycles,id'],
        ]);
        if ((isset($data['offering_id'])) === (isset($data['cycle_id']))) {
            throw ValidationException::withMessages(['offering_id' => 'Seleccione exactamente un grupo o un ciclo.']);
        }

        return $data;
    }
}
