@component('mail::message')
# Invitation expirée

L'invitation envoyée à {{ $invitation->invitee?->username ?? $invitation->invitee?->email }} a expiré.

@component('mail::panel')
Type : {{ class_basename($invitation->invitable_type) }}  
ID : {{ $invitation->invitable_id }}
@endcomponent

Merci,  
{{ config('app.name') }}
@endcomponent
