<?php

use App\Http\Requests\Playground\StorePlaygroundRequest;
use App\Http\Requests\Playground\UpdatePlaygroundRequest;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use Illuminate\Auth\Access\AuthorizationException;

it('allows authenticated store request validation outside docs generation', function () {
    config()->set('app.generating_api_docs', false);

    $user = User::factory()->create();
    $request = StorePlaygroundRequest::create('/api/playgrounds', 'POST');
    $request->setUserResolver(static fn () => $user);

    expect($request->authorize())->toBeTrue();
    expect($request->rules())->toHaveKey('slug');
});

it('rejects unauthenticated store request validation outside docs generation', function () {
    config()->set('app.generating_api_docs', false);

    $request = StorePlaygroundRequest::create('/api/playgrounds', 'POST');

    expect($request->authorize())->toBeFalse();
    expect(fn () => $request->rules())
        ->toThrow(AuthorizationException::class, 'Authentication required');
});

it('allows authenticated update request validation outside docs generation', function () {
    config()->set('app.generating_api_docs', false);

    $user = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    $request = makeUpdatePlaygroundRequest($playground);
    $request->setUserResolver(static fn () => $user);

    expect($request->authorize())->toBeTrue();
    expect($request->rules())->toHaveKey('slug');
});

it('rejects unauthenticated update request validation outside docs generation', function () {
    config()->set('app.generating_api_docs', false);

    $user = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    $request = makeUpdatePlaygroundRequest($playground);

    expect($request->authorize())->toBeFalse();
    expect(fn () => $request->rules())
        ->toThrow(AuthorizationException::class, 'Authentication required');
});

it('allows unauthenticated playground request validation during api docs generation', function () {
    config()->set('app.generating_api_docs', true);

    $storeRequest = StorePlaygroundRequest::create('/api/playgrounds', 'POST');

    expect($storeRequest->authorize())->toBeTrue();
    expect($storeRequest->rules())->toHaveKey('slug');

    $user = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    $updateRequest = makeUpdatePlaygroundRequest($playground);

    expect($updateRequest->authorize())->toBeTrue();
    expect($updateRequest->rules())->toHaveKey('slug');
});

function makeUpdatePlaygroundRequest(Playground $playground): UpdatePlaygroundRequest
{
    $request = UpdatePlaygroundRequest::create("/api/playgrounds/{$playground->playground_id}", 'PATCH');
    $route = new class($playground)
    {
        public function __construct(private readonly Playground $playground) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return $name === 'playground' ? $this->playground : $default;
        }
    };

    $request->setRouteResolver(static fn () => $route);

    return $request;
}
