@component('mail::message')
# Invitation a rejoindre le theme {{ $theme->title }}

Bonjour {{ $invitee->first_name ?: $invitee->username }},

**{{ $inviter->first_name ? $inviter->first_name . ' ' . $inviter->last_name : $inviter->username }}**
vous invite a rejoindre le theme **{{ $theme->title }}**.

@component('mail::button', ['url' => $acceptLink])
Accepter l'invitation
@endcomponent

@component('mail::button', ['url' => $declineLink, 'color' => 'red'])
Refuser l'invitation
@endcomponent

Si les boutons ne fonctionnent pas, utilisez ces liens :
- Accepter : {{ $acceptLink }}
- Refuser : {{ $declineLink }}

Merci,  
{{ config('app.name') }}
@endcomponent
