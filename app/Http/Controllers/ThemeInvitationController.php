<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use App\Invitations\Invitable;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ThemeInvitationController extends Controller
{
    public function respond(Request $request, string $invitationId): JsonResponse
    {
        $query = Validator::make($request->query(), [
            'status' => ['nullable', 'string', Rule::in(['accepted', 'declined']), 'required_without:action'],
            'action' => ['nullable', 'string', Rule::in(['accept', 'decline']), 'required_without:status'],
        ])->validate();

        $status = $query['status'] ?? null;
        if (! $status && isset($query['action'])) {
            $status = $query['action'] === 'accept' ? 'accepted' : 'declined';
        }

        $validated = $request->validate([
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id'],
        ]);

        $invitation = Invitation::where('invitation_id', $invitationId)
            ->where('status', 'pending')
            ->firstOrFail();

        if (! $request->user() || $invitation->invitee_user_id !== $request->user()->user_id) {
            throw new ApiException('permission.denied', [], 403, 'Permission denied');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->markExpired();
            throw new ApiException('invitation.expired', [], 403, 'Invitation expired');
        }

        if ($status === 'accepted') {
            $invitable = $invitation->invitable;

            if (! $invitable instanceof Invitable) {
                throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
            }

            $permission = $invitable->acceptInvitation(
                $invitation,
                $validated['target_playground_id'] ?? null
            );

            $invitation->markAccepted();

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

        $invitation->markDeclined();

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.declined', [
                'theme' => $invitation->invitable_id,
            ])
            ->json();
    }
}
