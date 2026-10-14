<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveCycleRequest extends AcademicRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('academic_cycles')->ignore($this->route('cycle'))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
