<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCycle;
use Illuminate\View\View;

class AcademicCycleController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.cycles.index', ['cycles' => AcademicCycle::withCount('offerings')->orderByDesc('starts_on')->get()]);
    }
}
