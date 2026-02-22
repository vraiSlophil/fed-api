<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::builder()
            ->success()
            ->data(auth()->user())
            ->messageCode('auth.user.fetched')
            ->json();
    }
}
