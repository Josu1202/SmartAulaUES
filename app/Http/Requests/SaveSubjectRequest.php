<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveSubjectRequest extends AcademicRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:30', Rule::unique('subjects')->ignore($this->route('subject'))], 'name' => ['required', 'string', 'max:255'], 'active' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
