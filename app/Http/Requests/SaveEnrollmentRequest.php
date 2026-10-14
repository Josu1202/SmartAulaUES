<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveEnrollmentRequest extends AcademicRequest
{
    public function rules(): array
    {
        $enrollment = $this->route('enrollment');

        return [
            'student_id' => $enrollment ? ['prohibited'] : ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')->where('active', true), Rule::unique('enrollments')->where('offering_id', $this->route('offering')->id)],
            'active_from' => ['required', 'date_format:Y-m-d'],
            'active_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:active_from'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
