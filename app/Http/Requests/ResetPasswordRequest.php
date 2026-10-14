<?php

namespace App\Http\Requests;

class ResetPasswordRequest extends AcademicRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
