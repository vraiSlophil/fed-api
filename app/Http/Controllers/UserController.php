<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Récupère les données de l'utilisateur actuellement connecté
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function current(Request $request): JsonResponse
    {
        // Débogage
//        Log::info('Session ID: ' . $request->session()->getId());
//        Log::info('Auth check: ' . (auth()->check() ? 'true' : 'false'));
//        Log::info('User: ' . (auth()->user() ? auth()->user()->email : 'null'));

//        $user = auth()->user();

//        if (!$user) {
//            return ApiResponse::error('Non authentifié', 401);
//        }

//        return ApiResponse::success($user, 'Utilisateur récupéré avec succès');

        Log::info('Cookies: ' . json_encode($request->cookies->all()));
        Log::info('Session ID: ' . $request->session()->getId());
        Log::info('User: ' . json_encode(auth()->user()));
//        Log::info(auth());
        Log::info(auth('sanctum')->user());
        Log::info($request->user());

//        $token = PersonalAccessToken::findToken($sactumToken);
//        $user = $token->tokenable;

        return ApiResponse::success(auth()->user(), 'Utilisateur récupéré avec succès');
    }
}
