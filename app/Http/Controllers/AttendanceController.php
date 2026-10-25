<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\CorrectAttendance;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Models\Attendance;
use App\Models\AttendancePortal;
use App\Models\ClassSession;
use App\Models\ReaderSession;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function open(Request $request, ClassSession $session, PortalManager $manager): JsonResponse
    {
        $data = $request->validate(['methods' => ['required', 'array', 'min:1', 'max:5'], 'methods.*' => ['required', 'in:nfc,qr,link,manual,nfc_tag', 'distinct'], 'minutes' => ['required', 'integer', 'between:1,60']]);
        $result = $manager->open($request->user(), $session, $data['methods'], $data['minutes'], $request->input('location'));
        $url = route('checkin.show', $result['portal']).'?method=link#t='.$result['token'];
        $qrUrl = route('checkin.show', $result['portal']).'?method=qr#t='.$result['token'];
        $png = in_array('qr', $data['methods']) ? (new Builder(writer: new PngWriter, data: $qrUrl, size: 280, margin: 12))->build()->getDataUri() : null;

        return response()->json(['portal_id' => $result['portal']->id, 'expires_at' => $result['portal']->expires_at->toIso8601String(), 'url' => in_array('link', $data['methods']) ? $url : null, 'qr' => $png, 'methods' => $data['methods'], 'nfc_tag_url' => in_array('nfc_tag', $data['methods']) ? route('checkin.show', $result['portal']).'?method=nfc_tag#t='.$result['token'] : null], 201);
    }

    public function close(Request $request, AttendancePortal $portal, PortalManager $manager): JsonResponse|RedirectResponse
    {
        $manager->close($request->user(), $portal);
        if ($request->expectsJson()) {
            return response()->json(['status' => 'closed']);
        }

        return to_route('sessions.show', $portal->session_id)->with('status', 'Registro cerrado.');
    }

    public function reader(Request $request, PortalManager $manager): JsonResponse
    {
        $data = $request->validate(['portal_id' => ['required', 'integer', 'exists:attendance_portals,id'], 'label' => ['required', 'string', 'max:100']]);
        $reader = $manager->reader($request->user(), AttendancePortal::findOrFail($data['portal_id']), $data['label']);

        return response()->json(['id' => $reader->id, 'expires_at' => $reader->expires_at->toIso8601String()], 201);
    }

    public function closeReader(Request $request, ReaderSession $readerSession, PortalManager $manager): JsonResponse
    {
        $manager->closeReader($request->user(), $readerSession);

        return response()->json(['status' => 'closed']);
    }

    public function nfc(Request $request, RegisterAttendance $register): JsonResponse
    {
        $data = $request->validate(['event_id' => ['required', 'uuid'], 'reader_session_id' => ['required', 'uuid', 'exists:reader_sessions,id'], 'credential_token' => ['required', 'string', 'regex:/\ASIAA:C1:[A-Za-z0-9_-]{43}\z/']]);
        $reader = ReaderSession::with('portal.session')->findOrFail($data['reader_session_id']);
        $result = $register->record($request->user(), $reader->portal->session, 'nfc', $data);

        return response()->json($result['body'], $result['http']);
    }

    public function manual(Request $request, ClassSession $session, RegisterAttendance $register): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['event_id' => ['required', 'uuid'], 'portal_id' => ['required', 'integer', 'exists:attendance_portals,id'], 'student_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $result = $register->record($request->user(), $session, 'manual', $data);
        if ($request->expectsJson()) {
            return response()->json($result['body'], $result['http']);
        }

        return to_route('sessions.show', $session)->with($result['http'] < 300 ? 'status' : 'error', $result['body']['message'] ?? 'Presencia registrada.');
    }

    public function correct(Request $request, ClassSession $session, Attendance $attendance, CorrectAttendance $correct): RedirectResponse
    {
        abort_unless($attendance->session_id === $session->id, 404);
        Gate::authorize('update', $session);
        $data = $request->validate(['status' => ['required', 'in:present,absent'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $correct->execute($request->user(), $attendance, $data['status'], $data['reason']);

        return to_route('sessions.show', $session)->with('status', 'Resultado corregido y auditado.');
    }
}
