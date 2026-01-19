<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Playground;
use App\Models\ThemeUserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeInvitationController extends Controller
{
    public function handleInvitation(Request $request): View
    {
        if (! $request->hasValidSignature()) {
            return view('theme.invitation-result', [
                'status' => 'error',
                'message' => 'Le lien d\'invitation est invalide ou a expiré.',
            ]);
        }

        $themeId = $request->theme_id;
        $userId = $request->user_id;
        $action = $request->action;

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->first();

        if (! $permission) {
            return view('theme.invitation-result', [
                'status' => 'error',
                'message' => 'L\'invitation n\'existe pas ou a déjà été traitée.',
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
        }

        if ($action === 'accept') {
            $defaultPlayground = Playground::where('user_id', $userId)
                ->where('is_default', true)
                ->first();

            $permission->status = 'active';
            $permission->target_playground_id = $defaultPlayground?->playground_id;
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
        }

        return view('theme.invitation', [
            'theme_id' => $themeId,
            'user_id' => $userId,
            'token' => $request->token,
            'signature' => $request->signature,
            'expires' => $request->expires,
        ]);
    }

    public function acceptInvitation(Request $request, string $themeId): JsonResponse
    {
        $userId = $request->user()->user_id;

        $validated = $request->validate([
            'target_playground_id' => 'required|uuid|exists:playgrounds,playground_id',
        ]);

        $playground = Playground::where('playground_id', $validated['target_playground_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->firstOrFail();

        $permission->update([
            'status' => 'active',
            'target_playground_id' => $validated['target_playground_id'],
        ]);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.accepted', [
                'theme' => $themeId,
                'target_playground_id' => $validated['target_playground_id'],
            ])
            ->data([
                'permission' => $permission->fresh(['theme', 'targetPlayground']),
            ])
            ->json();
    }

    public function declineInvitation(Request $request, string $themeId): JsonResponse
    {
        $userId = $request->user()->user_id;

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->firstOrFail();

        $permission->delete();

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.declined', [
                'theme' => $themeId,
            ])
            ->json();
    }
}
