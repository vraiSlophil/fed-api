<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'id' => ['required', 'string'],
            'hash' => ['required', 'string'],
        ])->validate();

        $id = $validated['id'];
        $givenHash = (string) $validated['hash'];

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

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(
                data: null,
                message: 'Email already verified',
                status: 200,
                messageCode: 'auth.verification.already_verified',
                messageParams: []
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success(
            message: 'Email verified',
            status: 200,
            data: null,
            messageCode: 'auth.verification.success',
            messageParams: []
        );
    }
}
