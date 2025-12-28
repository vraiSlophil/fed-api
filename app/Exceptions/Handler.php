<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($e instanceof AuthenticationException) {
            return ApiResponse::builder()
                ->error($e->getCode(), $e->getMessage())
                ->json();
        }

        return parent::render($request, $e);
    }
}
