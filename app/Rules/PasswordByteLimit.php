<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PasswordByteLimit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_contains($value, "\0")) {
            $fail('Escribe una contraseña sin caracteres de control.');
        } elseif (is_string($value) && strlen($value) > 72) {
            $fail('La contraseña es demasiado larga. Usa una más corta.');
        }
    }
}
