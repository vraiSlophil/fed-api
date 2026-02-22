<?php

namespace App\Http\Controllers\Invitations;

use App\Domain\Invitations\Actions\InvitationActionService;
use App\Domain\Invitations\Queries\InvitationQueryService;
use App\Domain\Invitations\Services\InvitationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\ListInvitationsRequest;
use App\Http\Requests\Invitation\RespondInvitationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Invitations\Invitation;
use App\Models\Themes\Theme;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;

class ThemeInvitationController extends Controller
{
    public function __construct(
        private readonly InvitationQueryService $queryService,
        private readonly InvitationActionService $actionService,
    ) {}

    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);
        $status = $validated['status'] ?? 'pending';

        $paginator = $this->queryService->paginateForInvitee($request->user(), $status, $pagination);

        $items = collect($paginator->items())
            ->map(fn (Invitation $invitation) => $this->toInvitationItem($invitation))
            ->values()
            ->all();

        return ApiResponse::builder()
            ->success()
            ->messageCode('invitation.list.success')
            ->data($items)
            ->meta(OffsetPagination::meta($paginator))
            ->json();
    }

    public function respond(
        RespondInvitationRequest $request,
        Invitation $invitation,
        InvitationService $invitationService,
    ): JsonResponse {
        $this->authorize('respond', $invitation);

        $validated = $request->validated();
        $result = $this->actionService->respond(
            $invitation,
            $validated['status'],
            $validated['target_playground_id'] ?? null,
            $invitationService,
        );

        if ($result['status'] === 'accepted') {
            return ApiResponse::builder()
                ->success()
                ->messageCode('theme.invitation.accepted', [
                    'theme' => $result['permission']->theme_id,
                    'target_playground_id' => $result['permission']->target_playground_id,
                ])
                ->data([
                    'permission' => $result['permission'],
                ])
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.declined', [
                'theme' => $invitation->invitable_id,
            ])
            ->json();
    }

    private function toInvitationItem(Invitation $invitation): array
    {
        $invitable = $invitation->invitable;

        return [
            'invitation_id' => $invitation->invitation_id,
            'status' => $invitation->status,
            'created_at' => $invitation->created_at,
            'expires_at' => $invitation->expires_at,
            'inviter' => [
                'user_id' => $invitation->inviter?->user_id,
                'username' => $invitation->inviter?->username,
                'email' => $invitation->inviter?->email,
                'first_name' => $invitation->inviter?->first_name,
                'last_name' => $invitation->inviter?->last_name,
                'avatar_path' => $invitation->inviter?->avatar_path,
            ],
            'invitable' => [
                'type' => strtolower(class_basename((string) $invitation->invitable_type)),
                'id' => $invitation->invitable_id,
                'title' => $invitable instanceof Theme ? $invitable->title : null,
                'color' => $invitable instanceof Theme ? $invitable->color : null,
            ],
        ];
    }
}
