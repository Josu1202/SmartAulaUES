<?php

namespace App\Http\Requests;

use App\Rules\PasswordByteLimit;

class ResetPasswordRequest extends AcademicRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:12', 'max:128', new PasswordByteLimit, 'confirmed'], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
