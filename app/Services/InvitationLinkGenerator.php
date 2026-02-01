<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Support\Facades\URL;

class InvitationLinkGenerator
{
    public function buildSignedLinks(Invitation $invitation): array
    {
        $expiresAt = $invitation->expires_at;

        $acceptSignedUrl = URL::temporarySignedRoute(
            'invitations.respond',
            $expiresAt,
            [
                'invitationId' => $invitation->invitation_id,
                'status' => 'accepted',
            ],
            false
        );

        $declineSignedUrl = URL::temporarySignedRoute(
            'invitations.respond',
            $expiresAt,
            [
                'invitationId' => $invitation->invitation_id,
                'status' => 'declined',
            ],
            false
        );

        return [
            'accept' => $this->buildFrontendInvitationUrl($acceptSignedUrl, $invitation->invitation_id),
            'decline' => $this->buildFrontendInvitationUrl($declineSignedUrl, $invitation->invitation_id),
        ];
    }

    private function buildFrontendInvitationUrl(string $signedApiUrl, string $invitationId): string
    {
        $frontendBase = rtrim(config('app.frontend_url'), '/');
        $pathTemplate = (string) config('app.frontend_invitation_path', '/invite/{invitationId}');
        $frontendPath = str_contains($pathTemplate, '{invitationId}')
            ? str_replace('{invitationId}', $invitationId, $pathTemplate)
            : (str_contains($pathTemplate, '{invitation}')
                ? str_replace('{invitation}', $invitationId, $pathTemplate)
                : $pathTemplate);

        $frontendPath = '/'.ltrim($frontendPath, '/');
        $query = parse_url($signedApiUrl, PHP_URL_QUERY);

        return $query ? $frontendBase.$frontendPath.'?'.$query : $frontendBase.$frontendPath;
    }
}
