<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.show.success')
            ->data([
                'user' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'avatar_path' => $user->avatar_path,
                ],
            ])
            ->json();
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users')->ignore($user->user_id, 'user_id')],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->user_id, 'user_id')],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user->update($validated);

        if ($user->wasChanged('email')) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();

            return ApiResponse::builder()
                ->success()
                ->messageCode('profile.update.email_changed', ['email_verification_sent' => true])
                ->data([
                    'user' => $user->only(['username', 'email', 'first_name', 'last_name', 'avatar_path', 'email_verified_at']),
                ])
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.update.success')
            ->data([
                'user' => $user->only(['username', 'email', 'first_name', 'last_name', 'avatar_path', 'email_verified_at']),
            ])
            ->json();
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw new ApiException('auth.failed', [], 422, 'Authentication failed');
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.password.updated')
            ->json();
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'max:2048',
                'mimes:jpeg,png,jpg,gif',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_path' => $path,
            'avatar_url' => Storage::disk('public')->url($path),
        ]);

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.avatar.updated')
            ->data([
                'avatar_path' => $user->avatar_path,
            ])
            ->json();
    }
}
