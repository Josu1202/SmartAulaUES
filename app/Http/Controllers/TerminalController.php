<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TerminalController extends Controller
{
    public function __invoke(): View
    {
        return view('terminal.index');
    }
}
