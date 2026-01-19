# Auth API (Sanctum) — fed-api

Ce projet utilise **Laravel Sanctum** en mode **Bearertoken** (API stateless).

## Objectif

- Éviter toute "pseudo-session" : **aucun endpoint ne doit se baser sur** `setUserResolver()`.
- L’unique mécanisme d’authentification côté API est :
  - `Authorization: Bearer <token>`
  - middleware `auth:sanctum`

## Endpoints

### POST `/api/login`

**Body**

- `email` (string, required)
- `password` (string, required)

**Réponse (succès)**

- `data.user` : modèle utilisateur (champs cachés via `$hidden`)
- `data.token` : token Sanctum `plainTextToken`

> Le token doit être stocké côté client (mobile / front) et envoyé ensuite via l’en-tête Authorization.

**Erreurs**

- `401` si identifiants invalides
- `429` si throttling (rate limiter) déclenché

### POST `/api/logout`

Nécessite un Bearer token.

- Révoque **le token courant** (`currentAccessToken()`)
- Fallback : si aucun token courant n’est détectable, révoque tous les tokens (cas atypique)

### Routes protégées

Toutes les routes privées sont sous :

- `auth:sanctum`

et celles nécessitant un email vérifié ajoutent :

- `verified` (custom middleware `EnsureEmailIsVerified`)

Exemple : `GET /api/ping` utilise `['auth:sanctum', 'verified']`.

## Pourquoi ce flow évite la pseudo-session

Avant : `LoginRequest::authenticate()` attachait l’utilisateur via `setUserResolver()`. Cela rendait `$request->user()` vrai *dans la requête courante* même sans vrai guard/token, ce qui peut masquer des erreurs (tests qui passent “par hasard”, endpoints qui oublient `auth:sanctum`, etc.).

Maintenant :

- `LoginRequest::authenticate()` délègue la vérification des identifiants à Laravel (`Auth::guard('web')->attempt(...)`).
- Il **retourne** explicitement le `User` authentifié.
- Le `LoginController` crée un token Sanctum via `$user->createToken(...)`.
- Les requêtes suivantes sont authentifiées uniquement si elles portent un token valide (middleware `auth:sanctum`).

## Tests

Les tests de flux sont dans `tests/Feature/Auth/AuthTokenFlowTest.php` :

- login renvoie un token et le token permet d’appeler une route protégée
- sans token, une route protégée renvoie 401
- logout révoque le token (le token ne fonctionne plus)

