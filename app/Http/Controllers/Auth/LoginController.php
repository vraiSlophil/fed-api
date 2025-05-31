<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Handle a login request to the application.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        if (auth()->attempt($credentials)) {

            return ApiResponse::success([
                'user' => auth()->user(),
                'token' => auth()->user()->createToken('auth_token')->plainTextToken,
                'token_type' => 'Bearer',
            ], 'Login successful', 200);
        }

        return ApiResponse::error('Invalid credentials', 401);
    }

    /**
     * Handle a logout request to the application.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        auth()->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout successful',
        ], 200);
    }
}
