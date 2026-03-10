<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read operations related to the current authenticated user.
 *
 * @group Users
 */
class UserController extends Controller
{
    /**
     * Return the currently authenticated user payload.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "auth.user.fetched",
     *   "data": {
     *     "user_id": "2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24",
     *     "username": "john",
     *     "email": "john@example.com",
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "role_power": 0
     *   }
     * }
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
