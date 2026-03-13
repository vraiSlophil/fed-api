@php
    $invitable = $invitation->invitable;
    $resourceType = class_basename($invitation->invitable_type);
    $resourceName = data_get($invitable, 'title')
        ?? data_get($invitable, 'name')
        ?? $resourceType;
    $inviterName = trim(($invitation->inviter?->first_name ?? '').' '.($invitation->inviter?->last_name ?? ''));
    $inviterDisplay = $inviterName !== ''
        ? $inviterName
        : ($invitation->inviter?->username ?? $invitation->inviter?->email ?? 'Un utilisateur');
@endphp

@component('mail::message')
# Invitation

Bonjour {{ $invitation->invitee?->first_name ?: $invitation->invitee?->username }},

**{{ $inviterDisplay }}** vous invite a rejoindre **{{ $resourceName }}** ({{ $resourceType }}).

Vous devrez vous connecter a l'application puis confirmer votre choix depuis l'ecran d'invitation.

@component('mail::button', ['url' => $acceptLink])
Ouvrir pour accepter
@endcomponent

@component('mail::button', ['url' => $declineLink, 'color' => 'red'])
Ouvrir pour refuser
@endcomponent

Si les boutons ne fonctionnent pas, utilisez ces liens :

- Ouvrir pour accepter : {{ $acceptLink }}
- Ouvrir pour refuser : {{ $declineLink }}

Merci,  
{{ config('app.name') }}
@endcomponent
