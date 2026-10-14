<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveOfferingRequest extends AcademicRequest
{
    public function rules(): array
    {
        return [
            'cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')->where('active', true)],
            'group_code' => ['required', 'string', 'max:20', Rule::unique('offerings')->where('cycle_id', $this->input('cycle_id'))->where('subject_id', $this->input('subject_id'))->ignore($this->route('offering'))],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
