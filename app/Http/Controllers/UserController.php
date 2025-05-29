<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
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
        Log::info('Session ID: ' . $request->session()->getId());
        Log::info('Auth check: ' . (auth()->check() ? 'true' : 'false'));
        Log::info('User: ' . (auth()->user() ? auth()->user()->email : 'null'));

        $user = auth()->user();

        if (!$user) {
            return ApiResponse::error('Non authentifié', 401);
        }

        return ApiResponse::success($user, 'Utilisateur récupéré avec succès');
    }
}
