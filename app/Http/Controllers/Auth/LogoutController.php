<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->actionService->logout($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.logout.success')
            ->json();
    }
}
