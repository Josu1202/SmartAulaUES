<?php

namespace App\Services\Attendance;

use App\Exceptions\RegistrationRejected;
use App\Models\AttendancePortal;
use App\Models\ClassSession;

class GeographicAccess
{
    public function requiresLocation(ClassSession $session): bool
    {
        return match ($session->effectiveAttendanceModality()) {
            'in_person' => true,
            'online' => false,
            default => $this->reject('La modalidad de la sesión no está configurada. Consulta al docente.', 'geo_configuration'),
        };
    }

    public function reading(mixed $input, bool $recent = true): array
    {
        if (! is_array($input)) {
            $this->reject('No hay una ubicación disponible. Permite el acceso y vuelve a intentar.', 'geo_unavailable');
        }
        $values = [];
        foreach (['latitude', 'longitude', 'accuracy', 'timestamp'] as $field) {
            $value = $input[$field] ?? null;
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                $this->reject('La ubicación recibida no es válida. Vuelve a obtenerla.', 'geo_invalid');
            }
            $values[$field] = (float) $value;
        }
        if (abs($values['latitude']) > 90 || abs($values['longitude']) > 180 || $values['accuracy'] <= 0 || $values['timestamp'] <= 0) {
            $this->reject('La ubicación recibida no es válida. Vuelve a obtenerla.', 'geo_invalid');
        }
        if ($values['accuracy'] > config('geolocation.max_accuracy_m')) {
            $obtained = number_format(ceil($values['accuracy'] * 100) / 100, 2, ',', '');
            $required = number_format(config('geolocation.max_accuracy_m'), 2, ',', '');
            $this->reject("La precisión obtenida (±{$obtained} m) supera el máximo de {$required} m por dispositivo. Busca mejor señal y reintenta, o utiliza NFC o registro manual.", 'geo_accuracy');
        }
        $age = now()->getTimestampMs() - $values['timestamp'];
        if ($recent && ($age > config('geolocation.max_evidence_age_ms') || $age < -config('geolocation.future_tolerance_ms'))) {
            $this->reject('La ubicación ha caducado o la hora del dispositivo no es válida. Obtén una nueva lectura.', 'geo_stale');
        }

        return $values;
    }

    public function validate(AttendancePortal $portal, ClassSession $session, mixed $input): ?array
    {
        $required = $this->requiresLocation($session);
        // New portals bind access policy to the modality at opening; legacy portals use current modality.
        if ($portal->access_modality !== null && $portal->access_modality !== $session->effectiveAttendanceModality()) {
            $this->reject('La modalidad cambió. El docente debe cerrar y reabrir el registro.', 'geo_configuration', 409);
        }
        if (! $required) {
            return null;
        }
        if ($portal->access_modality !== 'in_person' || ! is_array($portal->geo_reference)) {
            $this->reject('Este registro no tiene una referencia de ubicación válida. El docente debe cerrarlo y abrir uno nuevo.', 'geo_reference', 409);
        }
        try {
            $reference = $this->reading($portal->geo_reference, recent: false);
        } catch (RegistrationRejected) {
            $this->reject('La referencia de ubicación no es válida. El docente debe cerrar y reabrir el registro.', 'geo_reference', 409);
        }
        $openingAge = $portal->opens_at->getTimestampMs() - $reference['timestamp'];
        if ($openingAge > config('geolocation.max_evidence_age_ms') || $openingAge < -config('geolocation.future_tolerance_ms')) {
            $this->reject('La referencia de ubicación no es válida. El docente debe cerrar y reabrir el registro.', 'geo_reference', 409);
        }
        $reading = $this->reading($input);
        if (($input['portal_id'] ?? null) !== $portal->id || $reading['timestamp'] < $portal->opens_at->getTimestampMs() - config('geolocation.future_tolerance_ms')) {
            $this->reject('La ubicación no corresponde al registro vigente. Abre el enlace actual y reintenta.', 'geo_invalid');
        }
        $distance = $this->distance($reference, $reading);
        $uncertainty = $reference['accuracy'] + $reading['accuracy'];
        $radius = (float) config('geolocation.radius_m');
        if ($distance - $uncertainty > $radius) {
            $this->reject("Tu ubicación está fuera del radio permitido de {$radius} m respecto a la referencia del docente. Acércate y reintenta.", 'geo_outside');
        }
        if ($distance + $uncertainty > $radius) {
            $this->reject("La distancia más ambas incertidumbres supera el radio de {$radius} m respecto a la referencia del docente. Acércate y reintenta.", 'geo_accuracy');
        }

        // No student coordinates, reference coordinates or request payload are persisted here.
        return ['portal_id' => $portal->id, 'distance_m' => round($distance, 4), 'accuracy_m' => $reading['accuracy'],
            'reference_accuracy_m' => $reference['accuracy'], 'timestamp' => $reading['timestamp'], 'result' => 'inside'];
    }

    public function distance(array $first, array $second): float
    {
        $latitude = deg2rad($second['latitude'] - $first['latitude']);
        $longitude = deg2rad($second['longitude'] - $first['longitude']);
        $a = sin($latitude / 2) ** 2 + cos(deg2rad($first['latitude'])) * cos(deg2rad($second['latitude'])) * sin($longitude / 2) ** 2;

        return 6371008.8 * 2 * asin(sqrt(max(0.0, min(1.0, $a))));
    }

    private function reject(string $message, string $code, int $http = 422): never
    {
        throw new RegistrationRejected($message, $http, $code);
    }
}
