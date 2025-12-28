<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request)
    {
        // Journaliser les informations pour le débogage
        //        Log::info('Verification attempt', [
        //            'id' => $request->route('id'),
        //            'hash' => $request->route('hash')
        //        ]);

        // Trouver l'utilisateur par user_id
        $user = User::where('user_id', $request->route('id'))->first();

        if (! $user) {
            //            Log::error('User not found during verification', ['id' => $request->route('id')]);
            return view('auth.verify-email-result', [
                'status' => 'error',
                'message' => 'Utilisateur non trouvé',
            ]);
        }

        // Vérifier que le hash correspond
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            //            Log::error('Invalid hash during verification', [
            //                'id' => $request->route('id'),
            //                'hash' => $request->route('hash')
            //            ]);
            return view('auth.verify-email-result', [
                'status' => 'error',
                'message' => 'Lien de vérification invalide',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            //            Log::info('Email already verified', ['user_id' => $user->user_id]);
            return view('auth.verify-email-result', [
                'status' => 'info',
                'message' => 'Votre email a déjà été vérifié',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            //            Log::info('Email successfully verified', ['user_id' => $user->user_id]);
        }

        return view('auth.verify-email-result', [
            'status' => 'success',
            'message' => 'Votre email a été vérifié avec succès',
            'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
        ]);
    }
}
