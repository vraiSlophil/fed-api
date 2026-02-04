Issues à faire et ordre conseillé (authn + authz)
AuthN (authentification) – backend d’abord
#35 (backend) – Refresh token flow Sanctum pour BFF
(cookie HttpOnly, rotation, TTL, tests)
#21 (Backend)
#30 (backend) – Blocage utilisateur + auth.blocked + revoke tokens
#32 (backend) – Expiration tokens + revoke sur reset/change password
AuthN – frontend
#21 (front) – BFF Nuxt (SSR guards + server API proxy)
#19 (front) – (déjà mis à jour) appliquer la stratégie BFF (plus de localStorage)
AuthZ (autorisation)
#19 (backend) – Policy enforcement dans controllers (déjà existante)
#31 (backend) – Restrict searchUsers (anti-énumération)
#33 (backend) – Fix middleware alias verification (clarifier le verified)
#18 (front) – Admin guard aligné avec backend (role_power >= 100)
Invitations (in-app)
#34 (backend) – API RESTful invitations (list + patch/delete)
#20 (front) – Invitation center avec PATCH/DELETE REST

---

Constats critiques (par gravité)

Couplage fort au type Theme pour l’email d’invitation: InvitationService::sendCreatedEmail() bloque sur Theme et ThemeInvitation. Ça casse l’évolutivité pour Playground et d’autres invitable; il faudra un strategy/mapper par type (ex: méthode sur Invitable pour fournir mailable + payload). InvitationService.php.
Incohérence “failproof”: à la création, l’invitation est supprimée si la mise en file échoue, alors que l’expiration utilise des retries + log. Ça rend la création fragile et non‑rejouable. InvitationService.php.
Paramètre action quasi inutilisable: le contrôleur accepte action=accept|decline, mais la signature est générée avec status. Ajouter action rend la signature invalide; on garde donc un paramètre qui ne fonctionnera pas en pratique. ThemeInvitationController.php.
Réinvitation impossible après decline/expire: la contrainte unique bloque toute nouvelle invitation pour le même utilisateur/invitable même après expiration, ce qui peut devenir limitant selon le besoin produit. database_migrations_2026_02_01_000200_create_invitations_table.php.
Champ joined_at pour pending invites: en gardant une liste uniforme, joined_at est renseigné même pour status=invited (valeur = created_at). C’est cohérent pour l’API mais sémantiquement ambigu. ThemeMemberController.php.
