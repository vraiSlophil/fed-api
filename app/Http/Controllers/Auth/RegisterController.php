<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that creates accounts and issues tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Register a user account and return the initial authenticated session payload.
     *
     * @param  RegisterRequest  $request  Request carrying validated registration fields.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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
