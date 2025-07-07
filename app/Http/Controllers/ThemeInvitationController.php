<?php

namespace App\Http\Controllers;

use App\Models\ThemeUserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ThemeInvitationController extends Controller
{
    /**
     * Gérer l'acceptation ou le refus d'une invitation via le lien email
     */
    public function handleInvitation(Request $request)
    {
        // Vérifier que la requête a un lien signé valide
        if (!$request->hasValidSignature()) {
            return view('theme.invitation-result', [
                'status' => 'error',
                'message' => 'Le lien d\'invitation est invalide ou a expiré.',
            ]);
        }

        $themeId = $request->theme_id;
        $userId = $request->user_id;
        $action = $request->action;

        // Si l'utilisateur n'est pas connecté, le rediriger vers la page de connexion
        if (!Auth::check()) {
            // Sauvegarder l'URL actuelle dans la session pour y revenir après la connexion
            session(['url.intended' => url()->full()]);
            
            return redirect()->route('login');
        }

        // Vérifier que l'utilisateur connecté est bien celui invité
        if (Auth::id() !== $userId) {
            return view('theme.invitation-result', [
                'status' => 'error',
                'message' => 'Vous n\'êtes pas autorisé à utiliser cette invitation. Veuillez vous connecter avec le compte auquel l\'invitation a été envoyée.',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        }

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->first();

        if (!$permission) {
            return view('theme.invitation-result', [
                'status' => 'error',
                'message' => 'L\'invitation n\'existe pas ou a déjà été traitée.',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        }

        // Traiter l'action (accepter ou refuser)
        if ($action === 'accept') {
            $permission->status = 'active';
            $permission->save();

            return view('theme.invitation-result', [
                'status' => 'success',
                'message' => 'Vous avez accepté l\'invitation avec succès. Vous pouvez maintenant accéder au thème.',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        } elseif ($action === 'decline') {
            $permission->delete();

            return view('theme.invitation-result', [
                'status' => 'info',
                'message' => 'Vous avez refusé l\'invitation.',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        } else {
            // Si aucune action spécifiée, afficher les options
            return view('theme.invitation', [
                'theme_id' => $themeId,
                'user_id' => $userId,
                'token' => $request->token,
                'signature' => $request->signature,
                'expires' => $request->expires,
            ]);
        }
    }
}
