<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Authentication
 *
 * Endpoints for access tokens, refresh tokens, email verification, and credential recovery.
 */
class VerifyEmailController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that validates and applies email verification.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Validate signed verification parameters and mark the email as verified.
     *
     * @param  VerifyEmailRequest  $request  Request carrying validated signed verification query parameters.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @unauthenticated
     *
     * @responseFile 200 resources/docs/responses/success.json {"message":"Email verified","message_code":"auth.verification.success"}
     * @responseFile 403 resources/docs/responses/errors/invalid-signature.json
     */
    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->actionService->verifyEmail($validated['id'], (string) $validated['hash']);

        if ($result['already_verified']) {
            return ApiResponse::success(
                data: null,
                message: 'Email already verified',
                status: 200,
                messageCode: 'auth.verification.already_verified',
                messageParams: []
            );
        }

        return ApiResponse::success(
            data: null,
            message: 'Email verified',
            status: 200,
            messageCode: 'auth.verification.success',
            messageParams: []
        );
    }
}
