@component('mail::message')
# Invitation à rejoindre un thème

Bonjour {{ $invitee->first_name ?: $invitee->username }},

**{{ $inviter->first_name ? $inviter->first_name . ' ' . $inviter->last_name : $inviter->username }}** vous invite à rejoindre le thème **{{ $theme->title }}**.

@component('mail::button', ['url' => $acceptLink])
Accepter l'invitation
@endcomponent

@component('mail::button', ['url' => $declineLink, 'color' => 'red'])
Refuser l'invitation
@endcomponent

Si les boutons ne fonctionnent pas, vous pouvez également cliquer sur ce lien:
{{ $invitationLink }}

Merci,<br>
{{ config('app.name') }}
@endcomponent
