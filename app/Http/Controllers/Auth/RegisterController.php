<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::builder()
            ->success(201, 'Account created')
            ->messageCode('auth.register.success')
            ->data([
                'user' => $user,
                'token' => $token,
            ])
            ->json();
    }
}
