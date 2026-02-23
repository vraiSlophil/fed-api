<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\TokenService;
use App\Exceptions\ApiException;
use App\Models\Auth\RevokedRefreshToken;
use App\Models\Auth\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthActionService
{
    /**
     * Initialize the service with token issuance and rotation utilities.
     *
     * @param  TokenService  $tokenService  Service used to issue access and refresh token pairs.
     */
    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * Authenticate credentials and issue auth tokens.
     *
     * @param  User  $user  User account resolved from submitted credentials.
     * @param  Request  $request  HTTP request used to capture client IP metadata for the login event.
     * @return array Authentication payload containing user data and issued tokens.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function login(User $user, Request $request): array
    {
        if ($user->isBlocked()) {
            throw new ApiException('auth.blocked', [], 403, 'User blocked');
        }

        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $tokens = $this->tokenService->issueTokensFor($user);

        return [
            'user' => $user,
            ...$tokens,
        ];
    }

    /**
     * Register a new user and issue initial authentication tokens.
     *
     * @param  array  $validated  Validated payload extracted from the request.
     * @param  Request  $request  HTTP request used to capture client IP metadata for the registration event.
     * @return array Authentication payload containing user data and issued tokens.
     */
    public function register(array $validated, Request $request): array
    {
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make((string) $validated['password']),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        event(new Registered($user));

        $tokens = $this->tokenService->issueTokensFor($user);

        return [
            'user' => $user,
            ...$tokens,
        ];
    }

    /**
     * Revoke auth tokens for the current session.
     *
     * @param  ?User  $user  Authenticated user whose tokens should be revoked for logout.
     * @return void No return value.
     */
    public function logout(?User $user): void
    {
        if ($user) {
            $user->tokens()->delete();
        }
    }

    /**
     * Refresh the authentication token pair.
     *
     * @param  string  $refreshToken  Refresh token sent by the client.
     * @return array Authentication payload containing user data and issued tokens.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function refresh(string $refreshToken): array
    {
        if ($refreshToken === '') {
            throw new ApiException('auth.refresh.missing', [], 401, 'Refresh token missing');
        }

        $revoked = $this->findRevokedRefreshToken($refreshToken);
        if ($revoked) {
            if ($this->isWithinReuseGrace($revoked)) {
                $user = User::query()->find($revoked->user_id);
                if (! $user instanceof User) {
                    throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
                }

                if ($user->isBlocked()) {
                    throw new ApiException('auth.blocked', [], 403, 'User blocked');
                }

                return $this->tokenService->issueTokensFor($user);
            }

            $this->revokeAllTokensForUser($revoked->user_id);
            throw new ApiException('auth.refresh.reused', [], 401, 'Refresh token reused');
        }

        return DB::transaction(function () use ($refreshToken): array {
            $token = $this->findRefreshTokenForUpdate($refreshToken);

            if (! $token) {
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($token->cant(TokenService::REFRESH_ABILITY)) {
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($token->expires_at && $token->expires_at->isPast()) {
                $token->delete();
                throw new ApiException('auth.refresh.expired', [], 401, 'Refresh token expired');
            }

            $user = $token->tokenable;
            if (! $user instanceof User) {
                $token->delete();
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($user->isBlocked()) {
                throw new ApiException('auth.blocked', [], 403, 'User blocked');
            }

            $this->storeRevokedRefreshToken($token, $user);
            $token->delete();

            return $this->tokenService->issueTokensFor($user);
        });
    }

    /**
     * Send a password-reset link to the provided email address.
     *
     * @param  string  $email  Email address associated with the account.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            if ($user) {
                Log::error('Password reset email failed to dispatch', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }

            throw new ApiException(
                messageCode: 'auth.reset_link.failed',
                messageParams: ['reason' => 'dispatch_failed'],
                status: 500,
                message: 'Reset link could not be sent'
            );
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw new ApiException(
                messageCode: 'auth.reset_link.failed',
                messageParams: ['reason' => $status],
                status: 400,
                message: 'Reset link could not be sent'
            );
        }
    }

    /**
     * Reset the user password using a valid password-reset token.
     *
     * @param  array  $validated  Validated payload extracted from the request.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function resetPassword(array $validated): void
    {
        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function ($user) use ($validated): void {
                $user->forceFill([
                    'password' => Hash::make((string) $validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException(
                messageCode: 'auth.reset.failed',
                messageParams: ['reason' => $status],
                status: 400,
                message: 'Password reset failed'
            );
        }
    }

    /**
     * Verify a user's email address from signed route parameters.
     *
     * @param  string  $id  Opaque identifier provided by the signed verification URL.
     * @param  string  $givenHash  Email verification hash extracted from the signed URL.
     * @return array Payload containing the user and verification state after processing.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function verifyEmail(string $id, string $givenHash): array
    {
        try {
            $user = User::where('user_id', $id)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new ApiException(
                messageCode: 'resource.not_found',
                messageParams: ['resource' => 'user', 'id' => $id],
                status: 404,
                message: 'User not found'
            );
        }

        $expectedHash = sha1($user->getEmailForVerification());
        if (! hash_equals($expectedHash, $givenHash)) {
            throw new ApiException(
                messageCode: 'auth.verification.invalid',
                messageParams: [],
                status: 400,
                message: 'Invalid verification link'
            );
        }

        $alreadyVerified = $user->hasVerifiedEmail();

        if (! $alreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return [
            'user' => $user,
            'already_verified' => $alreadyVerified,
        ];
    }

    /**
     * Send an email-verification notification when the account is not yet verified.
     *
     * @param  User  $user  User account that should receive the verification notification.
     * @return bool True when the condition is met; otherwise, false.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function sendVerificationNotification(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Email verification notification failed to dispatch', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException('email.verification.failed', [], 500, 'Verification email failed');
        }

        return true;
    }

    /**
     * Persist a refresh token revocation record before rotating tokens.
     *
     * @param  PersonalAccessToken  $token  Sanctum personal access token model instance.
     * @param  User  $user  User account that owns the refresh token being revoked.
     * @return void No return value.
     */
    private function storeRevokedRefreshToken(PersonalAccessToken $token, User $user): void
    {
        RevokedRefreshToken::create([
            'token_id' => $token->getKey(),
            'user_id' => $user->getAuthIdentifier(),
            'token_hash' => $token->token,
            'revoked_at' => now(),
            'expires_at' => $token->expires_at,
        ]);
    }

    /**
     * Find a previously revoked refresh token by its raw token value.
     *
     * @param  string  $rawToken  Unencrypted token string handled by the authentication flow.
     * @return ?RevokedRefreshToken Matching revoked token record, or null when no match exists.
     */
    private function findRevokedRefreshToken(string $rawToken): ?RevokedRefreshToken
    {
        $hash = $this->hashToken($rawToken);

        if ($hash === null) {
            return null;
        }

        return RevokedRefreshToken::where('token_hash', $hash)->first();
    }

    /**
     * Determine whether a revoked refresh token is still within the reuse grace period.
     *
     * @param  RevokedRefreshToken  $revoked  Persisted revoked refresh token record.
     * @return bool True when the condition is met; otherwise, false.
     */
    private function isWithinReuseGrace(RevokedRefreshToken $revoked): bool
    {
        $graceSeconds = (int) config('auth_tokens.refresh_reuse_grace_seconds', 0);

        if ($graceSeconds <= 0 || $revoked->revoked_at === null) {
            return false;
        }

        return $revoked->revoked_at->addSeconds($graceSeconds)->isFuture();
    }

    /**
     * Find and lock a refresh token record before rotating it.
     *
     * @param  string  $rawToken  Unencrypted token string handled by the authentication flow.
     * @return ?PersonalAccessToken Locked refresh-token record, or null when the token is invalid.
     */
    private function findRefreshTokenForUpdate(string $rawToken): ?PersonalAccessToken
    {
        if ($rawToken === '') {
            return null;
        }

        if (str_contains($rawToken, '|')) {
            [$id, $plain] = explode('|', $rawToken, 2);
            if (! ctype_digit($id) || $plain === '') {
                return null;
            }

            $token = PersonalAccessToken::whereKey((int) $id)
                ->lockForUpdate()
                ->first();

            if (! $token) {
                return null;
            }

            return hash_equals($token->token, hash('sha256', $plain)) ? $token : null;
        }

        $hash = hash('sha256', $rawToken);

        return PersonalAccessToken::where('token', $hash)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Hash a raw refresh token into the storage lookup format.
     *
     * @param  string  $rawToken  Unencrypted token string handled by the authentication flow.
     * @return ?string SHA-256 token hash, or null when the incoming token is empty.
     */
    private function hashToken(string $rawToken): ?string
    {
        if ($rawToken === '') {
            return null;
        }

        if (str_contains($rawToken, '|')) {
            [, $rawToken] = explode('|', $rawToken, 2);
        }

        return hash('sha256', $rawToken);
    }

    /**
     * Revoke all personal access tokens belonging to the specified user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return void No return value.
     */
    private function revokeAllTokensForUser(string $userId): void
    {
        PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', User::class)
            ->delete();
    }
}
