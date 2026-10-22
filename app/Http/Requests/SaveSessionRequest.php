<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('offering'));
    }

    public function rules(): array
    {
        return ['period_id' => ['required', 'integer', Rule::exists('assessment_periods', 'id')->where('offering_id', $this->route('offering')->id)], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'], 'modality' => ['required', 'in:in_person,online,event'], 'event_attendance_mode' => ['nullable', 'required_if:modality,event', 'in:in_person,online'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
