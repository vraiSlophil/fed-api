<?php

namespace App\Http\Resources\Docs\Users;

use App\Http\Resources\Docs\ApiEnvelopeResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\User
 */
class CurrentUserResponseResource extends ApiEnvelopeResource
{
    protected ?string $messageCode = 'auth.user.fetched';

    protected function responseData(Request $request): mixed
    {
        return UserResource::make($this->resource)->resolve($request);
    }
}
