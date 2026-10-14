<?php

namespace App\Http\Requests;

class SaveCycleFormatRequest extends AcademicRequest
{
    public function rules(): array
    {
        return ['cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'], 'name' => ['required', 'string', 'max:120'],
            'active' => ['required', 'boolean'], 'periods' => ['required', 'array', 'min:1', 'max:12'],
            'periods.*.number' => ['required', 'integer', 'between:1,99', 'distinct'],
            'periods.*.starts_on' => ['required', 'date_format:Y-m-d'], 'periods.*.ends_on' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:1000']];
    }
}
