<?php

namespace App\Domain\Admin\Users\Actions;

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

    public function update(User $user, array $validated, ?\Illuminate\Http\UploadedFile $avatar = null): array
    {
        $data = [
            'username' => $validated['username'],
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'role_power' => $validated['role_power'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($avatar) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $avatar->store('avatars', 'public');
        }

        $emailChanged = $user->email !== $data['email'];
        $user->update($data);

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

        return [
            'user' => $user->fresh(),
            'email_changed' => $emailChanged,
        ];
    }

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

    public function block(User $target, User $actor): User
    {
        if ($target->blocked_at !== null) {
            throw new ApiException('user.block.already_blocked', [], 400);
        }

        if ($target->user_id === $actor->user_id) {
            throw new ApiException('user.block.forbidden_self', [], 400);
        }

        $target->update(['blocked_at' => now()]);

        return $target->fresh();
    }

    public function unblock(User $target): User
    {
        if ($target->blocked_at === null) {
            throw new ApiException('user.unblock.not_blocked', [], 400);
        }

        $target->update(['blocked_at' => null]);

        return $target->fresh();
    }
}
