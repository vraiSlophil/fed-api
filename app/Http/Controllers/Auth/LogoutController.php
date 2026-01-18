<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mode stateless: on privilégie le token Bearer envoyé.
        $rawToken = $request->bearerToken();

        if ($user && is_string($rawToken) && $rawToken !== '') {
            // Le plainTextToken est au format "id|secret".
            $secret = str_contains($rawToken, '|') ? explode('|', $rawToken, 2)[1] : $rawToken;

            $accessToken = PersonalAccessToken::where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getAuthIdentifier())
                ->where('token', hash('sha256', $secret))
                ->first();

            if ($accessToken) {
                $accessToken->delete();
            }

            return ApiResponse::builder()
                ->success()
                ->messageCode('auth.logout.success')
                ->json();
        }

        // Fallback: si Sanctum a résolu un token courant, le supprimer.
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } elseif ($user) {
            // Dernier recours: supprimer tous les tokens.
            $user->tokens()->delete();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.logout.success')
            ->json();
    }
}
