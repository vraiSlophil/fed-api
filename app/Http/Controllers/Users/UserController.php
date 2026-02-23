<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Return the currently authenticated user payload.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::builder()
            ->success()
            ->data(auth()->user())
            ->messageCode('auth.user.fetched')
            ->json();
    }
}
