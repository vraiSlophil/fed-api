<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use App\Invitations\Invitable;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ThemeInvitationController extends Controller
{
    public function respond(Request $request, string $invitationId, InvitationService $invitationService): JsonResponse
    {
        $query = Validator::make($request->query(), [
            'status' => ['required', 'string', Rule::in(['accepted', 'declined'])],
        ])->validate();

        $validated = $request->validate([
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id'],
        ]);

        $invitation = Invitation::where('invitation_id', $invitationId)->firstOrFail();

        if (!$request->user() || $invitation->invitee_user_id !== $request->user()->user_id) {
            throw new ApiException('permission.denied', [], 403, 'Permission denied');
        }

        if ($invitation->status === 'accepted' || $invitation->status === 'declined') {
            throw new ApiException('invitation.already_responded', [], 409, 'Invitation already responded');
        }

        if ($invitation->status === 'expired') {
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitationService->expireInvitation($invitation);
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        $status = $query['status'];

        if ($status === 'accepted') {
            $invitable = $invitation->invitable;

            if (!$invitable instanceof Invitable) {
                throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
            }

            $permission = $invitable->acceptInvitation(
                $invitation,
                $validated['target_playground_id'] ?? null
            );

            $invitationService->markAccepted($invitation);

            return ApiResponse::builder()
                ->success()
                ->messageCode('theme.invitation.accepted', [
                    'theme' => $permission->theme_id,
                    'target_playground_id' => $permission->target_playground_id,
                ])
                ->data([
                    'permission' => $permission->fresh(['theme', 'targetPlayground']),
                ])
                ->json();
        }

        $invitationService->markDeclined($invitation);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.declined', [
                'theme' => $invitation->invitable_id,
            ])
            ->json();
    }
}
