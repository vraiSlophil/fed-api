<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();
        $payload = $this->actionService->login($user, $request);

        return ApiResponse::builder()
            ->success()
            ->data($payload)
            ->messageCode('auth.login.success')
            ->json();
    }
}
