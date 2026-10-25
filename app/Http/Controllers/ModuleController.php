<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __invoke(Request $request, string $module): View|RedirectResponse
    {
        $modules = config('modules');
        abort_unless(array_key_exists($module, $modules), 404);
        $definition = $modules[$module];
        abort_unless(in_array($request->user()->role->value, $definition['roles'], true), 403);
        if ($module === 'reports') {
            return to_route('offerings.index')->with('status', 'Selecciona un grupo para consultar resultados e historial de cierres. La exportación PDF aún no está disponible.');
        }

        return view('modules.pending', compact('module', 'definition'));
    }
}
