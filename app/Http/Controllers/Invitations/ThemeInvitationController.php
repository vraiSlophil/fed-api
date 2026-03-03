<?php

namespace App\Http\Controllers\Invitations;

use App\Domain\Auth\Services\TokenService;
use App\Domain\Invitations\Actions\InvitationActionService;
use App\Domain\Invitations\Queries\InvitationQueryService;
use App\Domain\Invitations\Services\InvitationService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\ListInvitationsRequest;
use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Invitations\Invitation;
use App\Models\Themes\Theme;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;

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

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('theme.invite.sent', ['email' => $invitation->invitee?->email])
            ->data([
                'invitation' => $this->toInvitationItem($invitation),
            ])
            ->json();
    }

    /**
     * List invitations visible to the authenticated user.
     *
     * @param  ListInvitationsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);
        $status = $validated['status'] ?? 'pending';
        $scope = $validated['scope'] ?? 'inbox';

        $paginator = $this->queryService->paginateForUser($request->user(), $status, $scope, $pagination);

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

    /**
     * Return one invitation resource.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function show(Invitation $invitation): JsonResponse
    {
        $this->authorize('view', $invitation);

        return ApiResponse::builder()
            ->success()
            ->messageCode('invitation.show.success')
            ->data([
                'invitation' => $this->toInvitationItem($invitation->loadMissing(['inviter', 'invitable'])),
            ])
            ->json();
    }

    /**
     * Apply invitation status changes from authenticated or signed-link requests.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function respond(
        Request $request,
        Invitation $invitation,
        InvitationService $invitationService,
    ): JsonResponse {
        $user = $request->user('sanctum');
        $validated = [];

        if ($user) {
            if (! $user->currentAccessToken() || ! $user->tokenCan(TokenService::ACCESS_ABILITY)) {
                throw new AuthorizationException('Access token required');
            }

            $validated = $this->validateAuthenticatedResponsePayload($request);
            $status = (string) $validated['status'];

            if (in_array($status, ['accepted', 'declined'], true)) {
                $this->authorize('respondAcceptDecline', $invitation);
            } else {
                $this->authorize('cancel', $invitation);
            }
        } else {
            if (! $request->hasValidSignatureWhileIgnoring([], false)) {
                throw new InvalidSignatureException;
            }

            $validated = $this->validateSignedResponsePayload($request);
            $status = (string) $validated['status'];

            if ($status === 'canceled') {
                throw new AuthorizationException('Authentication required to cancel invitation');
            }
        }

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

    /**
     * Delete an invitation resource when its status allows hard deletion.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return Response HTTP response generated by the method.
     */
    public function destroy(Invitation $invitation): Response
    {
        $this->authorize('delete', $invitation);

        $this->actionService->delete($invitation);

        return ApiResponse::noContent();
    }

    /**
     * Transform an invitation model into the API response shape.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return array Serialized array representation of the resource.
     */
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

    /**
     * Validate authenticated invitation responses from request body only.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return array Validated response payload.
     */
    private function validateAuthenticatedResponsePayload(Request $request): array
    {
        $body = $this->bodyInput($request);

        return validator($body, [
            'status' => ['required', 'string', 'in:accepted,declined,canceled'],
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id', 'prohibited_unless:status,accepted'],
        ])->validate();
    }

    /**
     * Validate guest invitation responses using signed query status.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return array Validated response payload.
     *
     * @throws ValidationException
     */
    private function validateSignedResponsePayload(Request $request): array
    {
        $body = $this->bodyInput($request);
        if (array_key_exists('status', $body)) {
            throw ValidationException::withMessages([
                'status' => ['Status must be provided via signed query for unauthenticated requests.'],
            ]);
        }

        return validator([
            'status' => $request->query('status'),
            'target_playground_id' => $body['target_playground_id'] ?? null,
        ], [
            'status' => ['required', 'string', 'in:accepted,declined,canceled'],
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id', 'prohibited_unless:status,accepted'],
        ])->validate();
    }

    /**
     * Return parsed body payload without query-string values.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return array Parsed request body.
     */
    private function bodyInput(Request $request): array
    {
        if ($request->isJson()) {
            $json = $request->json()->all();

            return is_array($json) ? $json : [];
        }

        return $request->request->all();
    }
}
