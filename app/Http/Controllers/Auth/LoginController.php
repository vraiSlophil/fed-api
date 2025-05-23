<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            $request->session()->regenerate();

            $user = auth()->user();

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => $user,
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }
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
