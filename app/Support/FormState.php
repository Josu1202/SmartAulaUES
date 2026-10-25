<?php

namespace App\Support;

final class FormState
{
    public static function submitted(?string $scope): bool
    {
        return $scope === null || session()->getOldInput('_form') === $scope;
    }

    public static function old(?string $scope, string $name, mixed $default = null): mixed
    {
        return self::submitted($scope) ? old($name, $default) : $default;
    }
}
