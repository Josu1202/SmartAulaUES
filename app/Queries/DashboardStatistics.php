<?php

namespace App\Queries;

use App\Enums\Role;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\User;

class DashboardStatistics
{
    public function forUser(User $user): array
    {
        $sessions = ClassSession::whereHas('offering', fn ($q) => $q->visibleTo($user))->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $attendances = Attendance::whereHas('session', fn ($q) => $q->whereIn('state', ['in_progress', 'completed']))->whereHas('enrollment', fn ($q) => $q->visibleTo($user));
        $results = (clone $attendances)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $methods = (clone $attendances)->where('status', 'present')->whereNotNull('method')->selectRaw('method, count(*) as total')->groupBy('method')->pluck('total', 'method');
        $years = $user->role === Role::Coordinator ? User::where('role', 'student')->where('active', true)->selectRaw('study_year, count(*) as total')->groupBy('study_year')->orderBy('study_year')->get()->mapWithKeys(fn ($row) => [$row->study_year ? $row->study_year.'º año' : 'Sin asignar' => $row->total])->all() : [];

        return ['sessions' => ['Programadas' => (int) ($sessions['scheduled'] ?? 0), 'En curso' => (int) ($sessions['in_progress'] ?? 0), 'Realizadas' => (int) ($sessions['completed'] ?? 0), 'Canceladas' => (int) ($sessions['cancelled'] ?? 0)],
            'results' => ['Presentes' => (int) ($results['present'] ?? 0), 'Ausentes' => (int) ($results['absent'] ?? 0), 'Justificadas' => (int) ($results['justified'] ?? 0)],
            'methods' => ['NFC' => (int) ($methods['nfc'] ?? 0), 'QR' => (int) ($methods['qr'] ?? 0), 'Enlace' => (int) ($methods['link'] ?? 0), 'Manual' => (int) ($methods['manual'] ?? 0)], 'years' => $years,
            'pending_requests' => AbsenceRequest::where('status', 'pending')->whereHas('enrollment', fn ($q) => $q->visibleTo($user))->count()];
    }
}
