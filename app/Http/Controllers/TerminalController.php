<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TerminalController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('terminal.index', ['sessions' => ClassSession::where('state', 'in_progress')->whereHas('offering', fn ($q) => $q->visibleTo($request->user()))->with('offering.subject')->orderBy('starts_at')->get()]);
    }
}
