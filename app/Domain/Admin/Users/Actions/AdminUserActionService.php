<?php

namespace App\Domain\Admin\Users\Actions;

use App\Domain\Auth\Services\TokenService;
use App\Exceptions\ApiException;
use App\Models\Auth\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdminUserActionService
{
    /**
     * Initialize the service with token lifecycle utilities.
     *
     * @param  TokenService  $tokenService  Service used to revoke all active user tokens after admin password rotation.
     */
    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * Create a new user account.
     *
     * @param  array  $validated  Validated payload extracted from the request.
     * @param  ?\Illuminate\Http\UploadedFile  $avatar  Uploaded avatar file sent with the request.
     * @return User User instance returned after successful execution.
     */
    public function create(array $validated, ?\Illuminate\Http\UploadedFile $avatar = null): User
    {
        $data = [
            'username' => $validated['username'],
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'role_power' => $validated['role_power'],
            'password' => Hash::make($validated['password']),
        ];

        if ($avatar) {
            $data['avatar_path'] = $avatar->store('avatars', 'public');
        }

        $user = User::create($data);
        event(new Registered($user));

        return $user;
    }

    /**
     * Update an existing user account and optionally rotate avatar/password data.
     *
     * @param  User  $actor  Authenticated user who initiates the action.
     * @param  User  $target  User account being updated by the action.
     * @param  array  $validated  Validated payload extracted from the request.
     * @param  ?\Illuminate\Http\UploadedFile  $avatar  Uploaded avatar file sent with the request.
     * @return array Payload containing the updated user and email-change state.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function update(User $actor, User $target, array $validated, ?\Illuminate\Http\UploadedFile $avatar = null): array
    {
        $data = [];
        $isAdmin = $actor->role_power >= 100;

        foreach (['username', 'email', 'first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        if ($isAdmin) {
            if (array_key_exists('role_power', $validated)) {
                $data['role_power'] = $validated['role_power'];
            }

            if (array_key_exists('blocked_at', $validated)) {
                $data['blocked_at'] = $validated['blocked_at'];
            }
        }

        if (! empty($validated['password'])) {
            if (! $isAdmin && ! Hash::check($validated['current_password'], $target->password)) {
                throw new ApiException('auth.failed', [], 422, 'Authentication failed');
            }

            $data['password'] = Hash::make($validated['password']);
        }

        if ($avatar) {
            if ($target->avatar_path) {
                Storage::disk('public')->delete($target->avatar_path);
            }
            $data['avatar_path'] = $avatar->store('avatars', 'public');
        }

        $emailChanged = array_key_exists('email', $data) && $target->email !== $data['email'];

        if ($data !== []) {
            $target->update($data);
        }

        if (array_key_exists('password', $data)) {
            $this->tokenService->revokeAllTokensForUser((string) $target->getAuthIdentifier());
        }

        if ($emailChanged) {
            $target->email_verified_at = null;
            $target->save();
            try {
                $target->sendEmailVerificationNotification();
            } catch (Throwable $e) {
                Log::error('Email verification notification failed to dispatch', [
                    'user_id' => $target->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'user' => $target->fresh(),
            'email_changed' => $emailChanged,
        ];
    }

    /**
     * Permanently delete a user account while guarding against self-deletion.
     *
     * @param  User  $target  Target user instance affected by this operation.
     * @param  User  $actor  Authenticated user who initiates the action.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function delete(User $target, User $actor): void
    {
        if ($target->user_id === $actor->user_id) {
            throw new ApiException('user.delete.forbidden_self', [], 400);
        }

        try {
            if ($target->avatar_path) {
                Storage::disk('public')->delete($target->avatar_path);
            }

            $target->forceDelete();
        } catch (Exception $e) {
            if ($e instanceof QueryException && $e->getCode() === '23000') {
                throw new ApiException('user.delete.failed_foreign_key', [], 409);
            }

            throw new ApiException('user.delete.failed', [], 500);
        }
    }
}
