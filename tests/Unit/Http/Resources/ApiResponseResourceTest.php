<?php

use App\Http\Resources\Docs\Users\AdminUserIndexResponseCollection;
use App\Http\Resources\Docs\Users\CurrentUserResponseResource;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('serializes the admin user docs collection with the api envelope and custom pagination meta', function () {
    $users = collect([
        new User([
            'user_id' => (string) Str::uuid(),
            'username' => 'first-user',
            'email' => 'first@example.test',
            'first_name' => 'First',
            'last_name' => 'User',
            'avatar_path' => null,
            'role_power' => 10,
            'email_verified_at' => Carbon::parse('2026-03-10T10:00:00+00:00'),
            'blocked_at' => null,
            'last_login_at' => null,
            'created_at' => Carbon::parse('2026-03-01T10:00:00+00:00'),
            'updated_at' => Carbon::parse('2026-03-02T10:00:00+00:00'),
        ]),
        new User([
            'user_id' => (string) Str::uuid(),
            'username' => 'second-user',
            'email' => 'second@example.test',
            'first_name' => 'Second',
            'last_name' => 'User',
            'avatar_path' => null,
            'role_power' => 100,
            'email_verified_at' => Carbon::parse('2026-03-10T10:00:00+00:00'),
            'blocked_at' => null,
            'last_login_at' => null,
            'created_at' => Carbon::parse('2026-03-03T10:00:00+00:00'),
            'updated_at' => Carbon::parse('2026-03-04T10:00:00+00:00'),
        ]),
    ]);
    $paginator = new LengthAwarePaginator($users, 84, 15, 1);

    $response = (new AdminUserIndexResponseCollection($paginator))
        ->toResponse(Request::create('/api/users', 'GET'));

    $payload = $response->getData(true);

    expect($payload['status'])
        ->toBe('success')
        ->and($payload['message'])
        ->toBe('Ok')
        ->and($payload['meta']['current_page'])
        ->toBe(1)
        ->and($payload['meta']['per_page'])
        ->toBe(15)
        ->and($payload['meta']['total'])
        ->toBe(84)
        ->and($payload['meta']['last_page'])
        ->toBe(6)
        ->and($payload['meta']['has_next'])
        ->toBeTrue()
        ->and($payload['data'][0])
        ->toHaveKeys([
            'user_id',
            'username',
            'email',
            'first_name',
            'last_name',
            'avatar_path',
            'role_power',
            'email_verified_at',
            'blocked_at',
            'last_login_at',
            'created_at',
            'updated_at',
        ])
        ->and($payload)
        ->not->toHaveKey('links')
        ->and($payload['meta'])
        ->toHaveKeys([
            'sorting',
            'filters',
            'roles',
            'stats',
        ]);
});

it('serializes the current user docs resource with the api envelope', function () {
    $user = new User([
        'user_id' => (string) Str::uuid(),
        'username' => 'docs-user',
        'email' => 'docs@example.test',
        'first_name' => 'Docs',
        'last_name' => 'User',
        'avatar_path' => null,
        'role_power' => 10,
        'email_verified_at' => Carbon::parse('2026-03-10T10:00:00+00:00'),
        'blocked_at' => null,
        'last_login_at' => null,
        'created_at' => Carbon::parse('2026-03-01T10:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-02T10:00:00+00:00'),
    ]);

    $payload = (new CurrentUserResponseResource($user))
        ->resolve(Request::create('/api/users/me', 'GET'));

    expect($payload)
        ->toMatchArray([
            'status' => 'success',
            'message' => 'Ok',
            'message_code' => 'auth.user.fetched',
        ])
        ->and($payload['data'])
        ->toHaveKeys([
            'user_id',
            'username',
            'email',
            'first_name',
            'last_name',
            'avatar_path',
            'role_power',
        ]);
});
