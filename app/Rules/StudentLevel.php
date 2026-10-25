<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class StudentLevel implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_int($value) && ! is_string($value)) || ! in_array((string) $value, array_map('strval', array_keys(User::studyLevels())), true)) {
            $fail('Selecciona un año de 1.º a 5.º o Egresado.');
        }
    }
}
