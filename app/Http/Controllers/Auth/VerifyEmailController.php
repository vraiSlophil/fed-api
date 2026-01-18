<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = $request->route('id');

        try {
            $user = User::where('user_id', $id)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                throw new ApiException(
                    messageCode: 'resource.not_found',
                    messageParams: ['resource' => 'user', 'id' => $id],
                    status: 404,
                    message: 'User not found'
                );
            }

            return view('auth.verify-email-result', [
                'status' => 'error',
                'message' => 'Utilisateur non trouvé',
            ]);
        }

        $expectedHash = sha1($user->getEmailForVerification());
        $givenHash = (string)$request->route('hash');

        if (!hash_equals($expectedHash, $givenHash)) {
            if ($request->expectsJson()) {
                throw new ApiException(
                    messageCode: 'auth.verification.invalid',
                    messageParams: [],
                    status: 400,
                    message: 'Invalid verification link'
                );
            }

            return view('auth.verify-email-result', [
                'status' => 'error',
                'message' => 'Lien de vérification invalide',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            if ($request->expectsJson()) {
                return ApiResponse::success(
                    data: ['frontendUrl' => $frontendUrl],
                    message: 'Email already verified',
                    status: 200,
                    messageCode: 'auth.verification.already_verified',
                    messageParams: []
                );
            }

            return view('auth.verify-email-result', [
                'status' => 'info',
                'message' => 'Votre email a déjà été vérifié',
                'frontendUrl' => $frontendUrl
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        if ($request->expectsJson()) {
            return ApiResponse::success(
                message: 'Email verified',
                status: 200,
                data: ['frontendUrl' => $frontendUrl],
                messageCode: 'auth.verification.success',
                messageParams: []
            );
        }

        return view('auth.verify-email-result', [
            'status' => 'success',
            'message' => 'Votre email a été vérifié avec succès',
            'frontendUrl' => $frontendUrl
        ]);
    }
}
