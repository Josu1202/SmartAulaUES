<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\RegisterAttendance;
use App\Enums\Role;
use App\Models\AttendancePortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function show(Request $request, AttendancePortal $portal): View
    {
        if ($request->user()) {
            abort_unless($request->user()->active && $request->user()->role === Role::Student, 403);
            Gate::authorize('view', $portal->session);
            $portal->load('session.offering.subject');
        }

        return view('attendance.checkin', ['portal' => $portal, 'method' => $request->input('method') === 'qr' ? 'qr' : 'link']);
    }

    public function store(Request $request, AttendancePortal $portal, RegisterAttendance $register): JsonResponse
    {
        $data = $request->validate(['event_id' => ['required', 'uuid'], 'portal_token' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/'], 'method' => ['required', 'in:qr,link']]);
        if ($request->exists('location')) {
            $data['location'] = $request->input('location');
        }
        $result = $register->record($request->user(), $portal->session, $data['method'], [...$data, 'portal_id' => $portal->id]);

        return response()->json($result['body'], $result['http']);
    }
}
