<?php

namespace App\Http\Controllers\Profile;

use App\Domain\Profile\Actions\ProfileActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileAvatarRequest;
use App\Http\Requests\Profile\UpdateProfilePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Users\ProfileUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Initialize the controller with profile command handlers.
     *
     * @param  ProfileActionService  $actionService  Service that updates profile, password, and avatar fields.
     */
    public function __construct(private readonly ProfileActionService $actionService) {}

    /**
     * Return the authenticated user's profile payload.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.show.success')
            ->data(ProfileUserResource::make($user)->resolve())
            ->json();
    }

    /**
     * Update profile fields for the authenticated user.
     *
     * @param  UpdateProfileRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->actionService->update($request->user(), $request->validated());

        if ($user->email_verified_at === null && array_key_exists('email', $request->validated())) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('profile.update.email_changed', ['email_verification_sent' => true])
                ->data(ProfileUserResource::make($user)->resolve())
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.update.success')
            ->data(ProfileUserResource::make($user)->resolve())
            ->json();
    }

    /**
     * Update the authenticated user's password.
     *
     * @param  UpdateProfilePasswordRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function updatePassword(UpdateProfilePasswordRequest $request): JsonResponse
    {
        $this->actionService->updatePassword($request->user(), $request->validated());

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.password.updated')
            ->json();
    }

    /**
     * Update the authenticated user's avatar image.
     *
     * @param  UpdateProfileAvatarRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function updateAvatar(UpdateProfileAvatarRequest $request): JsonResponse
    {
        $path = $request->file('avatar')->store('avatars', 'public');
        $user = $this->actionService->updateAvatar($request->user(), $path);

        return ApiResponse::builder()
            ->success()
            ->messageCode('profile.avatar.updated')
            ->data([
                'avatar_path' => $user->avatar_path,
            ])
            ->json();
    }
}
