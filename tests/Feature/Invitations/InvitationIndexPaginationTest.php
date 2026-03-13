<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Laravel\Sanctum\Sanctum;

function createPaginatedInvitationForInvitee(User $inviter, string $inviterPlaygroundId, User $invitee, string $status = 'pending'): Invitation
{
    $theme = Theme::factory()->create([
        'owner_id' => $inviter->user_id,
        'playground_id' => $inviterPlaygroundId,
    ]);

    return Invitation::factory()->create([
        'inviter_user_id' => $inviter->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'permissions' => [
                'can_view' => true,
            ],
        ],
        'status' => $status,
        'expires_at' => now()->addDays(7),
    ]);
}

beforeEach(function () {
    $this->inviter = User::factory()->create();
    $inviterDefaultPlayground = Playground::where('user_id', $this->inviter->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $this->inviterPlaygroundId = $inviterDefaultPlayground->playground_id;
});

it('returns 401 when invitation listing is requested without authentication', function () {
    $this->getJson('/api/invitations')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('returns default paginated invitations with standard contract and user isolation', function () {
    $invitee = User::factory()->create();
    $otherInvitee = User::factory()->create();

    $otherUserInvitationIds = [];

    foreach (range(1, 20) as $_) {
        createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');
    }

    foreach (range(1, 3) as $_) {
        $otherUserInvitationIds[] = createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $otherInvitee, 'pending')->invitation_id;
    }

    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'accepted');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'declined');

    Sanctum::actingAs($invitee, ['access']);

    $response = $this->getJson('/api/invitations');

    $response->assertStatus(200)
        ->assertJsonPath('message_code', 'invitation.list.success')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 15)
        ->assertJsonPath('meta.has_next', true)
        ->assertJsonCount(15, 'data')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [[
                'invitation_id',
                'status',
                'created_at',
                'expires_at',
                'inviter' => [
                    'user_id',
                    'username',
                    'email',
                    'first_name',
                    'last_name',
                    'avatar_path',
                ],
                'invitable' => [
                    'type',
                    'id',
                    'title',
                    'color',
                ],
            ]],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
                'from',
                'to',
                'has_next',
            ],
        ]);

    $returnedStatuses = collect($response->json('data'))->pluck('status')->unique()->values()->all();
    expect($returnedStatuses)->toBe(['pending']);

    $returnedIds = collect($response->json('data'))->pluck('invitation_id');
    foreach ($otherUserInvitationIds as $otherInvitationId) {
        expect($returnedIds->contains($otherInvitationId))->toBeFalse();
    }
});

it('supports custom per_page in the standard contract', function () {
    $invitee = User::factory()->create();

    foreach (range(1, 20) as $_) {
        createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');
    }

    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?per_page=7')
        ->assertStatus(200)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 7)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 7)
        ->assertJsonPath('meta.has_next', true)
        ->assertJsonCount(7, 'data');
});

it('supports outbox scope when provided', function () {
    $inviteeA = User::factory()->create();
    $inviteeB = User::factory()->create();

    $outboxInvitationA = createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $inviteeA, 'pending');
    $outboxInvitationB = createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $inviteeB, 'pending');

    $otherInviter = User::factory()->create();
    $otherInviterPlayground = Playground::query()
        ->where('user_id', $otherInviter->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $inboxInvitation = createPaginatedInvitationForInvitee($otherInviter, $otherInviterPlayground->playground_id, $this->inviter, 'pending');

    Sanctum::actingAs($this->inviter, ['access']);

    $response = $this->getJson('/api/invitations?scope=outbox');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('invitation_id');
    expect($returnedIds->contains($outboxInvitationA->invitation_id))->toBeTrue();
    expect($returnedIds->contains($outboxInvitationB->invitation_id))->toBeTrue();
    expect($returnedIds->contains($inboxInvitation->invitation_id))->toBeFalse();
});

it('supports all scope and returns inbox and outbox invitations', function () {
    $invitee = User::factory()->create();
    $outboxInvitation = createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');

    $otherInviter = User::factory()->create();
    $otherInviterPlayground = Playground::query()
        ->where('user_id', $otherInviter->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $inboxInvitation = createPaginatedInvitationForInvitee($otherInviter, $otherInviterPlayground->playground_id, $this->inviter, 'pending');

    Sanctum::actingAs($this->inviter, ['access']);

    $response = $this->getJson('/api/invitations?scope=all');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('invitation_id');
    expect($returnedIds->contains($outboxInvitation->invitation_id))->toBeTrue();
    expect($returnedIds->contains($inboxInvitation->invitation_id))->toBeTrue();
});

it('returns 422 when per_page is lower than 1', function () {
    $invitee = User::factory()->create();
    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?per_page=0')
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('returns 422 when per_page exceeds the max', function () {
    $invitee = User::factory()->create();
    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?per_page=101')
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('returns empty items for out of bounds page while preserving pagination metadata', function () {
    $invitee = User::factory()->create();

    foreach (range(1, 3) as $_) {
        createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');
    }

    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?page=999&per_page=2')
        ->assertStatus(200)
        ->assertJsonPath('meta.current_page', 999)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.from', null)
        ->assertJsonPath('meta.to', null)
        ->assertJsonPath('meta.has_next', false)
        ->assertJsonCount(0, 'data');
});

it('supports status filter when provided', function () {
    $invitee = User::factory()->create();

    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'accepted');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'accepted');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'declined');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'canceled');

    Sanctum::actingAs($invitee, ['access']);

    $response = $this->getJson('/api/invitations?status=accepted');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    $statuses = collect($response->json('data'))->pluck('status')->unique()->values()->all();
    expect($statuses)->toBe(['accepted']);
});

it('returns 422 when status filter is invalid', function () {
    $invitee = User::factory()->create();
    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?status=wrong')
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('supports canceled status filter when provided', function () {
    $invitee = User::factory()->create();

    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'canceled');
    createPaginatedInvitationForInvitee($this->inviter, $this->inviterPlaygroundId, $invitee, 'pending');

    Sanctum::actingAs($invitee, ['access']);

    $this->getJson('/api/invitations?status=canceled')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'canceled');
});
