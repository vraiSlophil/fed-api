<?php

namespace App\Domain\Profile\Actions;

use App\Exceptions\ApiException;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileActionService
{
    /**
     * Update profile fields for the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return User User instance returned after successful execution.
     */
    public function update(User $user, array $validated): User
    {
        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;

        $user->update($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $e) {
                Log::error('Email verification notification failed to dispatch', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $user->fresh();
    }

    /**
     * Update the authenticated user's password after checking the current password.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function updatePassword(User $user, array $validated): void
    {
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw new ApiException('auth.failed', [], 422, 'Authentication failed');
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);
    }

    /**
     * Update the authenticated user's avatar and remove the previous file when present.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $uploadedPath  Storage path of the uploaded media file.
     * @return User User instance returned after successful execution.
     */
    public function updateAvatar(User $user, string $uploadedPath): User
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $uploadedPath]);

        return $user->fresh();
    }
}
