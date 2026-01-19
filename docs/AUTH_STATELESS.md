# Auth stateless (Sanctum Bearer tokens)

Ce projet utilise **Laravel Sanctum en mode Personal Access Tokens** (Bearer tokens) pour une API **100% stateless**.

- Pas de session serveur pour authentifier l’API.
- Chaque requête protégée doit contenir :

```
Authorization: Bearer <token>
```

> Remarque : Sanctum stocke les tokens en base (`personal_access_tokens`). Ce n’est **pas** une session :
> - pas d’état par requête côté serveur,
> - la DB sert uniquement de registre de révocation et de gestion multi-devices.

## Endpoints

### `POST /api/login`

- Valide `email` + `password`
- Applique le rate limiting (via `LoginRequest`)
- Si OK : crée un token Sanctum et renvoie `{ user, token }`

Réponse (exemple) :

```json
{
  "success": true,
  "data": {
    "user": { "user_id": "…", "email": "…" },
    "token": "<id>|<secret>"
  }
}
```

### `POST /api/logout`

Protégé par `auth:sanctum`.

- Révoque **le token courant** (`$request->user()->currentAccessToken()->delete()`)
- Le même Bearer token ne fonctionne plus ensuite.

## Routes protégées

Toutes les routes nécessitant un utilisateur authentifié doivent être sous :

- `auth:sanctum`

Et, si l’email doit être vérifié :

- `auth:sanctum` + `verified`

C’est déjà ce que fait `routes/api.php`.

## Vérification d’email (stack Nuxt 3, sans rendu HTML)

### Objectif

Arrêter de rendre une page HTML depuis l’API et laisser Nuxt afficher une page dédiée ("Email vérifié", "Lien invalide", etc.).

### Comment ça marche côté API

La route :

- `GET /api/verify-email/{id}/{hash}` (middleware `signed`, `throttle`)

ne dépend **pas** d’un guard web ou d’un utilisateur connecté.

C’est volontaire :
- l’utilisateur clique un lien depuis un email
- il n’a pas forcément de token au moment du clic
- la preuve est portée par la **signature** (`signed`) + le hash.

### Ce que Nuxt doit faire

Deux options propres :

#### Option A — Nuxt appelle l’API et affiche le résultat

1. L’utilisateur arrive sur une page Nuxt du type :

- `/verify-email?id=...&hash=...&expires=...&signature=...`

2. Nuxt appelle l’API :

- `GET /api/verify-email/{id}/{hash}?expires=...&signature=...`

3. Nuxt affiche selon la réponse JSON.

✅ Avantages : simple, pas de redirections

#### Option B — L’email pointe directement vers l’API, l’API redirige vers Nuxt

Aujourd’hui ton `VerifyEmailController` sait déjà faire HTML vs JSON (`expectsJson()`).

Si tu veux **supprimer totalement** le rendu HTML :
- on peut faire en sorte que l’API réponde toujours JSON
- et configurer la génération du lien pour pointer directement sur le front.

Le front appelle ensuite l’endpoint API pour finaliser.

## Invitations de thème (liens signés)

Même logique que la vérification email :

- le lien d’invitation est validé par la signature (`$request->hasValidSignature()`)
- il ne dépend pas de la session ou d’un token

### Recommandation

- Le lien email doit pointer vers une route Nuxt (ex: `/invite?theme_id=...&action=...&expires=...&signature=...`)
- Nuxt appelle l’API pour :
  - valider le lien (endpoint dédié recommandé)
  - puis, si l’utilisateur accepte/refuse, il le fait via les endpoints protégés (`auth:sanctum`) parce que là on veut être sûr que c’est bien le bon utilisateur connecté.

## Question : pourquoi `LoginRequest` n’est utilisé que dans le LoginController ?

`LoginRequest` est un **FormRequest** :
- il valide l’input du endpoint login
- il applique le rate limiting
- il encapsule la logique “vérifier email+password”

Il n’a pas vocation à être utilisé ailleurs.

🔑 Pour récupérer l’utilisateur dans les requêtes suivantes, c’est `auth:sanctum` qui s’en occupe :
- le middleware lit le Bearer token
- Sanctum hydrate `$request->user()`

## Question : pourquoi ne pas utiliser le guard `web` ?

Le guard `web` est conçu pour des apps **stateful** (sessions/cookies/CSRF).

Pour une API REST stateless :
- on évite `web`
- on utilise `auth:sanctum` avec Bearer tokens

Ça marche très bien et c’est aligné avec ton objectif “pas de pseudo-session”.

## Ce que le front (Nuxt) ne doit PAS faire : `/sanctum/csrf-cookie`

`/sanctum/csrf-cookie` est **uniquement** utile dans le mode "Sanctum SPA" (auth via cookies de session + CSRF).

Ici on est en mode **stateless Bearer tokens**, donc :

- ne pas appeler `/sanctum/csrf-cookie`
- ne pas utiliser `credentials: 'include'` (fetch) / `withCredentials: true` (axios)
- ne pas envoyer les cookies de session / XSRF vers l'API

Si tu vois des cookies `fed_api_session` / `XSRF-TOKEN` dans le navigateur, c'est généralement parce que :
- le front envoie des requêtes avec credentials (donc le navigateur accepte/renvoie des cookies), ou
- tu as gardé des cookies de tests précédents.

Concrètement : **ces cookies ne doivent pas participer au flow d'auth**.

## Intégration Nuxt 3 (Bearer token)

### Requêtes API

- Stocker le token (ex: Pinia + stockage persistant selon tes choix)
- Ajouter sur chaque call protégé :

`Authorization: Bearer <token>`

### Exemples (fetch)

- **Ne pas** mettre `credentials: 'include'`.
- Ajouter l'en-tête `Authorization`.

### Exemples (axios)

- **Ne pas** configurer `withCredentials`.
- Ajouter l'en-tête `Authorization` via un interceptor.
