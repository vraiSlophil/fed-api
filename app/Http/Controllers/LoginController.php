<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthenticationException;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!auth()->attempt($credentials)) {
            throw new AuthenticationException('Les identifiants fournis sont incorrects');
        }

        return ApiResponse::success(
            auth()->user(),
            'Connexion réussie'
        );
    }
}
