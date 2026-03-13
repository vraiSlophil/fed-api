<?php

namespace App\Domain\Invitations\Services;

use App\Models\Invitations\Invitation;

class InvitationLinkGenerator
{
    /**
     * Build frontend deep links for invitation email entry points.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return array Structured set of generated links.
     */
    public function buildInboxLinks(Invitation $invitation): array
    {
        return [
            'accept' => $this->buildFrontendInvitationUrl($invitation->invitation_id, 'accept'),
            'decline' => $this->buildFrontendInvitationUrl($invitation->invitation_id, 'decline'),
        ];
    }

    /**
     * Build the frontend invitation URL with an optional UI-only intent.
     *
     * @param  string  $invitationId  Identifier of the invitation.
     * @param  ?string  $intent  Optional frontend-only action hint.
     * @return string Frontend invitation URL that routes users into the invitation screen.
     */
    private function buildFrontendInvitationUrl(string $invitationId, ?string $intent = null): string
    {
        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $pathTemplate = (string) config('app.frontend_invitation_path', '/invite/{invitationId}');
        $frontendPath = str_contains($pathTemplate, '{invitationId}')
            ? str_replace('{invitationId}', $invitationId, $pathTemplate)
            : (str_contains($pathTemplate, '{invitation}')
                ? str_replace('{invitation}', $invitationId, $pathTemplate)
                : $pathTemplate);

        $frontendPath = '/'.ltrim($frontendPath, '/');
        $frontendUrl = $frontendBase.$frontendPath;

        if ($intent === null) {
            return $frontendUrl;
        }

        return $frontendUrl.'?'.http_build_query(['intent' => $intent]);
    }
}
