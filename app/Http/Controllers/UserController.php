<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::builder()
            ->success()
            ->data(auth()->user())
            ->json();
    }
}
