<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

abstract class AcademicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active && $this->user()?->role === Role::Coordinator;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['code', 'group_code', 'student_code'] as $key) {
            if ($this->has($key)) {
                $values[$key] = $this->input($key) === null ? null : mb_strtoupper(trim((string) $this->input($key)));
            }
        }
        foreach (['name', 'reason'] as $key) {
            if ($this->has($key)) {
                $values[$key] = trim((string) $this->input($key));
            }
        }
        if ($this->has('email')) {
            $values['email'] = mb_strtolower(trim((string) $this->input('email')));
        }
        $this->merge($values);
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'role' => 'perfil', 'student_code' => 'carné', 'password' => 'contraseña', 'code' => 'código', 'starts_on' => 'fecha inicial', 'ends_on' => 'fecha final', 'cycle_id' => 'ciclo', 'subject_id' => 'materia', 'teacher_id' => 'docente', 'group_code' => 'grupo', 'capacity' => 'cupo', 'student_id' => 'estudiante', 'active_from' => 'inicio de vigencia', 'active_to' => 'fin de vigencia', 'number' => 'número del cómputo', 'reason' => 'motivo'];
    }
}
