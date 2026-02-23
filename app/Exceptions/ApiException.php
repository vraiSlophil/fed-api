<?php

namespace App\Exceptions;

use Exception;
use Throwable;

final class ApiException extends Exception
{
    /**
     * Build a domain-level API exception with transport metadata.
     *
     * @param  string  $messageCode  Machine-readable message code for i18n or client branching.
     * @param  array  $messageParams  Placeholder values interpolated by the translation layer.
     * @param  int  $status  HTTP status code exposed by the API response.
     * @param  string  $message  Human-readable message returned to the client.
     * @param  ?Throwable  $previous  Previous exception chained to this error.
     */
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
