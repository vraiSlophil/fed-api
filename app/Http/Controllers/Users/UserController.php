<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Users
 *
 * Endpoints for reading and mutating user accounts from the authenticated application context.
 */
class UserController extends Controller
{
    /**
     * Return the currently authenticated user payload.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @apiResource App\Http\Resources\Docs\Users\CurrentUserResponseResource
     *
     * @apiResourceModel App\Models\Auth\User
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     */
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::builder()
            ->success()
            ->data(UserResource::make($request->user())->resolve())
            ->messageCode('auth.user.fetched')
            ->json();
    }
}
