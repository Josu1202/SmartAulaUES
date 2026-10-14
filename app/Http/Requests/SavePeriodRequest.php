<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SavePeriodRequest extends AcademicRequest
{
    public function rules(): array
    {
        return ['number' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('assessment_periods')->where('offering_id', $this->route('offering')->id)->ignore($this->route('period'))], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
