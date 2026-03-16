<?php

namespace App\Http\Controllers\Invitations;

use App\Domain\Invitations\Actions\InvitationActionService;
use App\Domain\Invitations\Queries\InvitationQueryService;
use App\Domain\Invitations\Services\InvitationService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\ListInvitationsRequest;
use App\Http\Requests\Invitation\RespondInvitationRequest;
use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Http\Resources\Invitations\InvitationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invitations\Invitation;
use App\Models\Themes\Theme;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Invitations
 *
 * Endpoints for creating, listing, inspecting, and deleting invitation resources.
 */
class ThemeInvitationController extends Controller
{
    /**
     * Initialize the controller with invitation query and command handlers.
     *
     * @param  InvitationQueryService  $queryService  Service that reads invitation lists and related data.
     * @param  InvitationActionService  $actionService  Service that applies invitation response commands.
     */
    public function __construct(
        private readonly InvitationQueryService $queryService,
        private readonly InvitationActionService $actionService,
    ) {}

    /**
     * Create a new invitation resource.
     *
     * @param  StoreInvitationRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 201 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "theme.invite.sent",
     *   "data": {
     *     "invitation": {
     *       "invitation_id": "3cc56a5e-43e4-4ff2-a33d-8475c6bbf79a",
     *       "status": "pending",
     *       "created_at": "2026-03-10T10:00:00+00:00",
     *       "expires_at": "2026-03-17T10:00:00+00:00",
     *       "inviter": {
     *         "user_id": "2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24",
     *         "username": "owner",
     *         "email": "owner@example.com",
     *         "first_name": "Owner",
     *         "last_name": "User",
     *         "avatar_path": null
     *       },
     *       "invitable": {
     *         "type": "theme",
     *         "id": "278fdd58-2050-4556-9393-8195d1a4ed74",
     *         "title": "Roadmap",
     *         "color": "#2563EB"
     *       }
     *     }
     *   }
     * }
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function store(StoreInvitationRequest $request, InvitationService $invitationService): JsonResponse
    {
        $validated = $request->validated();

        if (in_array($validated['invitable_type'], ['theme', Theme::class], true)) {
            $theme = Theme::query()
                ->where('theme_id', (string) $validated['invitable_id'])
                ->firstOrFail();

            $this->authorize('manageMembers', $theme);
        } else {
            throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
        }

        $invitation = $this->actionService->create($request->user(), $validated, $invitationService);
        $invitation->loadMissing(['inviter', 'invitable']);

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('theme.invite.sent', ['email' => $invitation->invitee?->email])
            ->data([
                'invitation' => InvitationResource::make($invitation)->resolve(),
            ])
            ->json();
    }

    /**
     * List invitations visible to the authenticated user.
     *
     * @param  ListInvitationsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @apiResourceCollection App\Http\Resources\Docs\Invitations\InvitationIndexResponseCollection
     *
     * @apiResourceModel App\Models\Invitations\Invitation paginate=15
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);
        $status = $validated['status'] ?? 'pending';
        $scope = $validated['scope'] ?? 'inbox';

        $paginator = $this->queryService->paginateForUser($request->user(), $status, $scope, $pagination);

        return ApiResponse::builder()
            ->success()
            ->messageCode('invitation.list.success')
            ->data(InvitationResource::collection($paginator->items())->resolve())
            ->meta(OffsetPagination::meta($paginator))
            ->json();
    }

    /**
     * Return one invitation resource.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"invitation.show.success","data":{"invitation":{"invitation_id":"3cc56a5e-43e4-4ff2-a33d-8475c6bbf79a","status":"pending"}}}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function show(Invitation $invitation): JsonResponse
    {
        $this->authorize('view', $invitation);

        return ApiResponse::builder()
            ->success()
            ->messageCode('invitation.show.success')
            ->data([
                'invitation' => InvitationResource::make($invitation->loadMissing(['inviter', 'invitable']))->resolve(),
            ])
            ->json();
    }

    /**
     * Apply invitation status changes from the authenticated invitation inbox.
     *
     * Invitation email links only open the frontend invitation screen. The actual
     * mutation must always be submitted through this authenticated API endpoint.
     *
     * @param  RespondInvitationRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 200 scenario="Accepted" {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "theme.invitation.accepted",
     *   "data": {
     *     "permission": {
     *       "theme_id": "278fdd58-2050-4556-9393-8195d1a4ed74",
     *       "user_id": "9ab53fb4-a4ae-44ec-a2ef-e0f9df9d5c6a",
     *       "target_playground_id": "5e4f4aa4-a102-4878-8b86-9623a02f2f01"
     *     }
     *   }
     * }
     * @response 200 scenario="Declined" {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "theme.invitation.declined",
     *   "data": null
     * }
     * @response 200 scenario="Canceled" {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "theme.invitation.canceled",
     *   "data": null
     * }
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     *
     * @response 409 {
     *   "status": "error",
     *   "message": "Only pending invitations can transition",
     *   "message_code": "invitation.invalid_transition",
     *   "data": null
     * }
     * @response 410 {
     *   "status": "error",
     *   "message": "Invitation expired",
     *   "message_code": "invitation.expired",
     *   "data": null
     * }
     *
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
    public function respond(
        RespondInvitationRequest $request,
        Invitation $invitation,
        InvitationService $invitationService,
    ): JsonResponse {
        $validated = $request->validated();
        $status = (string) $validated['status'];

        if (in_array($status, ['accepted', 'declined'], true)) {
            $this->authorize('respondAcceptDecline', $invitation);
        } else {
            $this->authorize('cancel', $invitation);
        }

        return $this->buildResponsePayload($invitation, $validated, $invitationService);
    }

    /**
     * Delete an invitation resource when its status allows hard deletion.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return Response HTTP response generated by the method.
     *
     * @response 204 scenario="No Content"
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function destroy(Invitation $invitation): Response
    {
        $this->authorize('delete', $invitation);

        $this->actionService->delete($invitation);

        return ApiResponse::noContent();
    }

    /**
     * Build the API response after a validated invitation transition.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  array<string, mixed>  $validated  Validated input for the transition.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    private function buildResponsePayload(
        Invitation $invitation,
        array $validated,
        InvitationService $invitationService,
    ): JsonResponse {
        $result = $this->actionService->respond(
            $invitation,
            (string) $validated['status'],
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

        if ($result['status'] === 'canceled') {
            return ApiResponse::builder()
                ->success()
                ->messageCode('theme.invitation.canceled', [
                    'theme' => $invitation->invitable_id,
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
}
