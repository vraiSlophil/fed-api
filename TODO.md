Issues à faire et ordre conseillé (authn + authz)
AuthN (authentification) – backend d’abord
#35 (backend) – Refresh token flow Sanctum pour BFF
(cookie HttpOnly, rotation, TTL, tests)
#21 (Backend)
#43 (Backend)
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
