<?php

namespace App\Exceptions;

class RegistrationRejected extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422, public readonly ?string $reasonCode = null)
    {
        parent::__construct($message);
    }
}
