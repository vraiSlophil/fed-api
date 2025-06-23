<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Http\Responses\ApiResponse;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                $e->getMessage(),
                $e->getCode()
            );
        }

        return parent::render($request, $e);
    }
}
