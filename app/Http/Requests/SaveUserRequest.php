<?php

namespace App\Http\Requests;

use App\Rules\PasswordByteLimit;
use App\Rules\StudentLevel;
use Illuminate\Validation\Rule;

class SaveUserRequest extends AcademicRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in($this->route('directory_role') ? [$this->route('directory_role')] : ['coordinator', 'teacher', 'student'])],
            'student_code' => [Rule::requiredIf($this->input('role') === 'student'), 'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('users')->ignore($user)],
            'active' => ['required', 'boolean'],
            'entry_year' => [Rule::requiredIf($this->route('directory_role') === 'student'), 'nullable', 'integer', 'between:1900,2200'],
            'study_year' => [Rule::requiredIf($this->route('directory_role') === 'student'), 'nullable', new StudentLevel],
            'password' => $user ? ['prohibited'] : ['required', 'string', 'min:12', 'max:128', new PasswordByteLimit, 'confirmed'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
