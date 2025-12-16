# fed\-api

API REST Laravel pour l\'application front `fed\-webapp` (voir le repo GitHub `https://github.com/vraiSlophil/fed-webapp`).  
Cette API fournit les endpoints nécessaires au front et s\'utilise exclusivement via Docker.

---

## 1. Contexte du projet

- **Back**: API REST en Laravel 12 (PHP \>= 8\.2\)
- **Front**: projet `fed\-webapp` (React) disponible sur le repo GitHub `https://github.com/vraiSlophil/fed-webapp`
- **Base de données**: PostgreSQL (via `docker-compose`)
- **Emailing**: service Resend (via `resend/resend-php`)
- **Authentification**: Laravel Sanctum

Le front doit être cloné et installé séparément en suivant le `README` du repo `fed\-webapp`.

---

## 2. Prérequis

Sur la machine de développement, il faut au minimum :

- Docker
- Docker Compose
- Accès à une clé API **Resend** (créée sur le site de Resend)

Aucune exécution locale "sans Docker" n\'est supportée officiellement.

---

## 3. Installation

### 3\.1. Cloner le projet

```bash
git clone git@github.com:vraiSlophil/fed-api.git
cd fed-api
```

### 3\.2. Fichier d\'environnement

Copier le fichier `\.env.example` en `\.env` :

```bash
cp .env.example .env
```

Puis modifier au minimum :

- `APP_ENV=development`
- `APP_URL=http://localhost:8000`
- `APP_FRONTEND_URL=http://localhost:3000` (ou l\'URL du front réel)
- `RESEND_API_KEY=...` (clé d\'API Resend valide)

**Recommandation** : chaque développeur doit utiliser **sa propre clé Resend** (créée sur son compte) pour éviter de partager un secret commun et pour faciliter le suivi / la révocation. Le partage d\'une clé unique d\'un lead est à éviter.

---

## 4. Démarrage avec Docker

Tout se fait via `docker-compose`.

### 4\.1. Premier build

```bash
docker compose build
```

### 4\.2. Lancer les conteneurs

```bash
docker compose up -d
```

- L\'API est exposée sur `http://localhost:8000`
- PostgreSQL tourne dans le service `postgres`
- PgAdmin est disponible sur `http://localhost:8080` (login/mot de passe définis dans `\.env`)

### 4\.3. Migrations et clé d\'application

Après le premier démarrage, exécuter les commandes suivantes dans le conteneur `laravel` :

```bash
# entrer dans le conteneur
docker compose exec laravel bash

# générer la clé d'application Laravel
php artisan key:generate

# exécuter les migrations
php artisan migrate
```

Optionnellement, pour lancer les seeders :

```bash
# seed de base (DatabaseSeeder appelle RolesSeeder et CompleteDataSeeder)
php artisan db:seed
```

Les seeders fournis (`RolesSeeder`, `CompleteDataSeeder`, `UsersSeeder`, etc.) permettent d\'avoir un jeu de données complet (utilisateurs, rôles, thèmes, tâches, permissions, métriques). Ils ne sont pas indispensables au bon fonctionnement, mais utiles pour les environnements de développement / démo.

---

## 5. Seeders et jeu de données

Les principaux seeders sont :

- `DatabaseSeeder`  
  \- appelle `RolesSeeder` (création des rôles `user`, `admin`, `superadmin`)  
  \- appelle `CompleteDataSeeder` (jeu de données complet)

- `RolesSeeder`  
  \- insère les rôles de base dans la table `roles`

- `CompleteDataSeeder`  
  \- crée différents types d\'utilisateurs (super\-admins, admins, utilisateurs, bloqués)  
  \- génère des métriques utilisateurs (`UserMetric`)  
  \- crée des thèmes (`Theme`) avec un propriétaire  
  \- crée des permissions par thème (`ThemeUserPermission`) selon le rôle de l\'utilisateur  
  \- crée des tâches (`Task`) avec différents statuts, dates de validation et d\'archivage  
  \- affiche un résumé en console (nombre d\'utilisateurs par type, thèmes, tâches, permissions)

Pour lancer uniquement ce jeu de données complet si besoin, adapter `DatabaseSeeder` ou appeler le seeder à la main :

```bash
php artisan db:seed --class=CompleteDataSeeder
```

---

## 6. Lancement en mode développement

Il n\'y a pas aujourd\'hui de script npm / watcher spécifique documenté côté API.  
Le flux standard est :

```bash
docker compose up -d

# puis, si besoin de commandes artisan spécifiques :
docker compose exec laravel bash
php artisan <commande>
```

Le front `fed\-webapp` doit être démarré séparément en suivant son propre `README`.

---

## 7. Emails (Resend)

- Le driver mail est configuré pour utiliser **Resend** via la variable `MAIL_MAILER=resend`.
- Il faut une **clé d\'API Resend** valide dans `RESEND_API_KEY`.
- La clé s\'obtient en créant un compte sur `https://resend.com` puis en générant un token API.
- Par bonnes pratiques de sécurité, chacun doit utiliser **sa clé personnelle** en environnement de dev.  
  En staging / prod, les clés seront gérées par l\'équipe devops / lead et stockées dans un gestionnaire de secrets.

---

## 8. Workflow Git et contributions

- **Branche principale**: `main`
- **Branche de développement**: `dev` (cible des PR)
- **Branches de feature**:
    - Format: `feat/<nom-feature>`  
      \- ex: `feat/auth-login`, `feat/user-profile`
- **[Conventional Commit](https://www.conventionalcommits.org/fr/v1.0.0/) obligatoires**:
    - Exemples :  
      \- `feat: add user metrics aggregation`  
      \- `fix: correct theme permissions seeder`  
      \- `chore: update dependencies`  
      \- `refactor: split task service`

Flux recommandé :

1. Créer une branche à partir de `dev` :  
   `git checkout dev` puis `git switch -C feat/<nom-feature>`
2. Commits au format Conventional Commit.
3. Ouvrir une Pull Request de `feat/<nom-feature>` vers `dev`.

Les règles détaillées de revue, de CI/CD, de qualité de code (Pint, PHPStan, etc.) et d\'intégration continue seront définies et mises en place par l\'équipe de développement qui arrive.

---

## 9. Tests

- Aucune suite de tests n\'est encore en place.
- Les dépendances de dev incluent déjà Pest et le plugin Laravel, ce qui permet de mettre en place des tests rapidement à l\'avenir.
- La stack de tests (Pest vs PHPUnit, e2e, etc.) reste à décider et à documenter par l\'équipe.

---

## 10. Licence

Le projet est actuellement sous licence **MIT** (voir `composer.json`).
