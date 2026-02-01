@component('mail::message')
# Invitation refusée

L'utilisateur {{ $invitation->invitee?->username ?? $invitation->invitee?->email }} a refusé votre invitation.

@component('mail::panel')
Type : {{ class_basename($invitation->invitable_type) }}  
ID : {{ $invitation->invitable_id }}
@endcomponent

Merci,  
{{ config('app.name') }}
@endcomponent
