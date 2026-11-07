<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SmartAulaUES · Reporte de cómputo</title>
    <style>
        @page { margin: 24mm 14mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #202020; }
        h1 { color: #a61824; font-size: 17px; margin-bottom: 3px; }
        h2 { font-size: 12px; margin-top: 18px; }
        .muted { color: #555; }
        .meta { margin: 12px 0; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; font-size: 8px; }
        thead { display: table-header-group; background: #efefef; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #aaa; padding: 5px 4px; vertical-align: top; }
        th { text-align: left; }
        .number { text-align: right; white-space: nowrap; }
        .note { margin-top: 12px; font-size: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    @php($snapshot = $report->snapshot)
    <h1>SmartAulaUES</h1>
    <div class="muted">Reporte histórico de asistencia · Versión {{ $report->version }}</div>
    <div class="meta">
        <strong>Ciclo:</strong> {{ $period->offering->cycle->code }} ·
        <strong>Materia:</strong> {{ $period->offering->subject->name }} ·
        <strong>Grupo:</strong> {{ $period->offering->group_code }}<br>
        <strong>Docente asignado:</strong> {{ $period->offering->teacher->name }}<br>
        <strong>Cómputo:</strong> {{ $snapshot['period']['number'] }} ·
        <strong>Desde:</strong> {{ $snapshot['period']['starts_on'] }} ·
        <strong>Hasta:</strong> {{ $snapshot['period']['ends_on'] }}<br>
        <strong>Estado del documento:</strong> Resultado de cierre conservado ·
        <strong>Versión de política:</strong> {{ $snapshot['policy_version'] }} ·
        <strong>Umbral del proyecto:</strong> {{ $snapshot['policy']['minimum_percentage'] ?? 'No disponible' }} %<br>
        <strong>Fecha de corte UTC:</strong> {{ $snapshot['generated_at'] }} ·
        <strong>Versión generada:</strong> {{ $report->generated_at?->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') ?? 'Sin fecha' }}
    </div>
    <h2>Resultados por estudiante</h2>
    <table>
        <thead>
            <tr>
                <th>Estudiante / Carné</th>
                <th class="number">Presentes</th>
                <th class="number">Ausentes</th>
                <th class="number">Justificadas</th>
                <th class="number">Elegibles</th>
                <th class="number">Asistencia</th>
                <th>Estado del resultado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($snapshot['rows'] as $row)
                @php($calculation = $row['calculation'])
                <tr>
                    <td>{{ $row['student_name'] }}<br><span class="muted">{{ $row['student_code'] ?? 'Sin carné' }}</span></td>
                    <td class="number">{{ $calculation['counts']['present'] ?? 0 }}</td>
                    <td class="number">{{ $calculation['counts']['absent'] ?? 0 }}</td>
                    <td class="number">{{ $calculation['counts']['justified'] ?? 0 }}</td>
                    <td class="number">{{ $calculation['counts']['eligible'] ?? 0 }}</td>
                    <td class="number">{{ $calculation['percentage'] === null ? '—' : $calculation['percentage'].' %' }}</td>
                    <td>{{ $calculation['conclusion'] ?? match($calculation['state']) { 'no_sessions' => 'Sin sesiones evaluables', 'incomplete' => 'Resultado incompleto', default => 'Sin conclusión disponible' } }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No hay inscripciones en esta versión de cierre.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">
        Las ausencias justificadas se presentan por separado. Las clases canceladas o sin inicio no generan ausencia.
        Cuando no existen sesiones elegibles, el porcentaje se muestra como «—», no como cero.
        La política corresponde a la versión registrada en el cierre; su aprobación institucional no se presume.
        La identificación actual del grupo y del docente puede haber cambiado desde la generación del snapshot.
        Los resultados individuales proceden exclusivamente del snapshot histórico y no de consultas de asistencia en vivo.
    </p>
</body>
</html>
