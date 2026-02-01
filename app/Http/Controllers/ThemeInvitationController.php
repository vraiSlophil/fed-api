<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use App\Models\Playground;
use App\Models\ThemeUserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ThemeInvitationController extends Controller
{
    public function respond(Request $request, string $invitation): JsonResponse
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

        $permission = ThemeUserPermission::where('permission_id', $invitation)
            ->where('status', 'invited')
            ->firstOrFail();

        if (! $request->user() || $permission->user_id !== $request->user()->user_id) {
            throw new ApiException('permission.denied', [], 403, 'Permission denied');
        }

        if ($status === 'accepted') {
            $targetPlaygroundId = $validated['target_playground_id'] ?? null;

            if ($targetPlaygroundId) {
                Playground::where('playground_id', $targetPlaygroundId)
                    ->where('user_id', $permission->user_id)
                    ->firstOrFail();
            } else {
                $defaultPlayground = Playground::where('user_id', $permission->user_id)
                    ->where('is_default', true)
                    ->first();

                $targetPlaygroundId = $defaultPlayground?->playground_id;
            }

            $permission->update([
                'status' => 'active',
                'target_playground_id' => $targetPlaygroundId,
            ]);

            return ApiResponse::builder()
                ->success()
                ->messageCode('theme.invitation.accepted', [
                    'theme' => $permission->theme_id,
                    'target_playground_id' => $targetPlaygroundId,
                ])
                ->data([
                    'permission' => $permission->fresh(['theme', 'targetPlayground']),
                ])
                ->json();
        }

        $permission->delete();

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.invitation.declined', [
                'theme' => $permission->theme_id,
            ])
            ->json();
    }
}
