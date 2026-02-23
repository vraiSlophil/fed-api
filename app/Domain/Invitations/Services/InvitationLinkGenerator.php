<?php

namespace App\Domain\Invitations\Services;

use App\Models\Invitations\Invitation;
use Illuminate\Support\Facades\URL;

class InvitationLinkGenerator
{
    /**
     * Build signed accept/decline links for an invitation.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return array Structured set of generated links.
     */
    public function buildSignedLinks(Invitation $invitation): array
    {
        $expiresAt = $invitation->expires_at;

        $acceptSignedUrl = URL::temporarySignedRoute(
            'invitations.respond',
            $expiresAt,
            [
                'invitation' => $invitation->invitation_id,
                'status' => 'accepted',
            ],
            false
        );

        $declineSignedUrl = URL::temporarySignedRoute(
            'invitations.respond',
            $expiresAt,
            [
                'invitation' => $invitation->invitation_id,
                'status' => 'declined',
            ],
            false
        );

        return [
            'accept' => $this->buildFrontendInvitationUrl($acceptSignedUrl, $invitation->invitation_id),
            'decline' => $this->buildFrontendInvitationUrl($declineSignedUrl, $invitation->invitation_id),
        ];
    }

    /**
     * Convert a signed API URL into the frontend invitation URL format.
     *
     * @param  string  $signedApiUrl  Absolute URL passed to downstream logic.
     * @param  string  $invitationId  Identifier of the invitation.
     * @return string Frontend invitation URL that preserves the signed query string.
     */
    private function buildFrontendInvitationUrl(string $signedApiUrl, string $invitationId): string
    {
        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
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
