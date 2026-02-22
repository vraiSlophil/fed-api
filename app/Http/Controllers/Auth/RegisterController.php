<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $payload = $this->actionService->register($request->validated(), $request);

        return ApiResponse::builder()
            ->success(201, 'Account created')
            ->messageCode('auth.register.success')
            ->data($payload)
            ->json();
    }
}
