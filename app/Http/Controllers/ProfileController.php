<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Afficher les informations du profil
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => [
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_path' => $user->avatar_path,
            ]
        ]);
    }

    /**
     * Mettre à jour les informations du profil
     */
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

        // Si l'email a été modifié, on le marque comme non vérifié
        if ($user->wasChanged('email')) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        return ApiResponse::success([
            'user' => $user->only(['username', 'email', 'first_name', 'last_name', 'avatar_path'])
        ], 'Profile updated successfully');
    }

    /**
     * Mettre à jour le mot de passe
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error('The provided password does not match your current password.', 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return ApiResponse::success(null, 'Password updated successfully');
    }

    /**
     * Mettre à jour l'avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'max:2048',
                'mimes:jpeg,png,jpg,gif',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000'
            ]
        ]);

        $user = $request->user();

        // Supprimer l'ancien avatar s'il existe
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Stocker le nouvel avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_path' => $path,
            'avatar_path' => Storage::disk('public')->url($path)
        ]);

        return ApiResponse::success([
            'avatar_path' => $user->avatar_path
        ], 'Avatar updated successfully');
    }
}
