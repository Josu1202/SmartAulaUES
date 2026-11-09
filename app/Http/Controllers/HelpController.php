<?php

namespace App\Http\Controllers;

use App\Services\Help\TutorialCatalog;
use App\Services\Help\TutorialProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HelpController extends Controller
{
    public function index(Request $request, TutorialCatalog $catalog, TutorialProgressService $progress): View
    {
        return view('help.index', [
            'steps' => $catalog->forRole($request->user()->role),
            'status' => $progress->status($request->user()),
            'version' => TutorialCatalog::VERSION,
        ]);
    }

    public function skip(Request $request, TutorialProgressService $progress): RedirectResponse
    {
        $progress->mark($request->user(), TutorialProgressService::SKIPPED);

        return back()->with('status', 'Recorrido omitido. Puede repetirlo desde Ayuda.');
    }

    public function complete(Request $request, TutorialProgressService $progress): RedirectResponse
    {
        $progress->mark($request->user(), TutorialProgressService::COMPLETED);

        return back()->with('status', 'Recorrido completado. Puede repetirlo desde Ayuda.');
    }
}
