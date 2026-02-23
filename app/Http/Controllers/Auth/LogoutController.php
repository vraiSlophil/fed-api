<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that revokes active authentication tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Revoke tokens for the authenticated user and terminate the current session.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->actionService->logout($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.logout.success')
            ->json();
    }
}
