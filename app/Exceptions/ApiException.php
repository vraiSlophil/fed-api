<?php

namespace App\Exceptions;

use Exception;
use Throwable;

final class ApiException extends Exception
{
    public function __construct(
        public readonly string $messageCode,
        public readonly array $messageParams = [],
        public readonly int $status = 400,
        string $message = 'Error',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }
}
