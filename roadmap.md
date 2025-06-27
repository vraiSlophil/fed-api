# Plan de travail : API REST pour le projet FED

Ci-dessous une « todo-list » structurée des éléments à développer côté back-end pour couvrir l’ensemble des besoins décrits.  
Chaque section indique :

• Les ressources principales  
• Les points d’architecture / sécurité / DX à prévoir  
• Les endpoints REST (signature minimale)

---

## 1. Authentification & sécurité de base

| Objectif | Actions à implémenter |
|----------|----------------------|
| Authentification stateless | `POST /auth/login`, `POST /auth/logout`, `POST /auth/refresh` (si tokens expirables) |
| Inscription & vérification d’e-mail | `POST /auth/register`, `GET /auth/verify/{id}/{hash}` |
| Réinitialisation de mot de passe | `POST /auth/forgot-password`, `POST /auth/reset-password` |
| Middleware Sanctum | Protection des routes + throttling |
| Rate limiting & CORS | Paramétrage global pour SPA |
| Headers de sécurité | HSTS, X-Content-Type-Options, etc. |

---

## 2. Gestion du profil utilisateur

| Endpoint | Verbe | Payload / Query | Description |
|----------|-------|-----------------|-------------|
| `/me` | GET | – | Récupération du profil connecté |
| `/me` | PATCH | `{ first_name, last_name, username, email }` | MAJ infos personnelles |
| `/me/password` | PATCH | `{ current_password, new_password }` | Changement de MDP |
| `/me/avatar` | POST (multipart) | `file` | Upload / remplacement d’avatar |
| `/me/avatar` | DELETE | – | Suppression de l’avatar |

Points d’implémentation :  
• Validation unique sur `username` et `email` – renvoyer 409 en cas de conflit.  
• Stockage des avatars sur disque (Flysystem) + génération d’URL signée.  
• Mécanisme soft-delete si vous souhaitez un mode « désactivation compte ».

---

## 3. Ressource « Thème » (workspace)

| Endpoint | Verbe | Payload | Notes |
|----------|-------|---------|-------|
| `/themes` | GET | `?page, per_page` | Liste des thèmes où l’utilisateur a accès |
| `/themes` | POST | `{ title, color }` | Création |
| `/themes/{id}` | GET | – | Détails + liste brève des tâches |
| `/themes/{id}` | PATCH | `{ title?, color? }` | MAJ (propriétaire uniquement) |
| `/themes/{id}` | DELETE | – | Suppression (soft-delete, seulement pour soi si partagé) |

Architecture :  
• Propriétaire (`owner_id`) + pivot `theme_user_permissions` (droit lecture/écriture).  
• Logiciels : Policy Laravel + Gate pour restreindre accès.

---

## 4. Partage & permissions

| Endpoint | Verbe | Payload | Description |
|----------|-------|---------|-------------|
| `/themes/{id}/share` | POST | `{ username }` | Ajout d’un collaborateur |
| `/themes/{id}/share/{userId}` | DELETE | – | Retirer un collaborateur |
| `/themes/{id}/permissions/{userId}` | PATCH | `{ can_write: bool }` | (Optionnel) ajuster droits |

À prévoir :  
• Notification in-app + e-mail quand on est ajouté.  
• Si l’utilisateur se retire lui-même → suppression de l’entrée pivot seulement.

---

## 5. Ressource « Tâche »

| Endpoint | Verbe | Payload | Notes |
|----------|-------|---------|-------|
| `/themes/{themeId}/tasks` | GET | `?status, page` | Liste (colle à l’ordre custom) |
| `/themes/{themeId}/tasks` | POST | `{ title }` | Création |
| `/tasks/{taskId}` | PATCH | `{ title?, is_done?, position? }` | MAJ générale |
| `/tasks/{taskId}` | DELETE | – | Suppression définitive |
| `/tasks/{taskId}/move` | PATCH | `{ new_position }` | Glisser-déposer (ordonnancement) |
| `/tasks/{taskId}/archive` | PATCH | `{ archived: true|false }` | Envoi / sortie de la cardbox |

À prévoir :  
• Colonne `position` (float ou int + gap).  
• Colonne `archived_at` pour la cardbox.  
• Events Laravel + broadcast (Pusher / WebSockets) si temps réel souhaité.

---

## 6. Notifications in-app

Resource : `/notifications`

• GET, PATCH (mark-as-read) – utilise `database` channel Laravel.  
• Générées sur : partage thème, nouvelle tâche assignée, modification de permission, etc.

---

## 7. Audit log & métriques

1. AuditLog (CRUD caché)  
   • Observer sur modèles Theme/Task/User pour créer un log (`who`, `action`, `before`, `after`).  
   • Endpoint admin read-only : `/admin/audit-logs`.

2. UserMetric  
   • Stocker temps moyen entre création et complétion, nombre de tâches par jour, etc.  
   • Cron (scheduler) pour les calculs nightly.  
   • Endpoint `/me/metrics` GET.

---

## 8. Administration (facultatif mais conseillé)

| Ressource | Endpoints |
|-----------|-----------|
| Rôles & permissions système | `/admin/roles`, `/admin/users` |
| Health check | `/admin/health` → état DB, queue |
| Gestion de files | `/admin/queues` (visu jobs échoués) |

---

## 9. Infrastructure technique

• Files de messages : `database` queue → migrer vers Redis si montée en charge.  
• Jobs asynchrones : envoi d’e-mails, traitement d’avatars (redimensionnement).  
• Cache : `themes:{userId}` pour accélérer listes.  
• Pagination/Laravel JSON API Resources pour standardiser réponses (`ApiResponse` déjà présent).

---

## 10. Tests automatisés (PHPUnit)

Priorité haute :

1. Auth (login, refresh, accès protégé)
2. Droit d’accès sur /themes et /tasks (owner / collaborator / stranger)
3. Ordonnancement & cardbox (position update, archive toggle)
4. Partage supprimé → pas d’effet chez propriétaire

---

## 11. Documentation & DX

• OpenAPI (Swagger) généré automatiquement (`knuckleswtf/scribe` ou `laravel-swagger`).  
• Postman collection exportable.  
• README décrivant setup Docker (compose déjà présent).

---

## 12. Versionnage & release

• Tag Git + migrations idempotentes.  
• Stratégie de break change : `/v1` prefix quand la première version est stabilisée.

---

### Checklist synthétique

- [ ] Auth : login / register / logou profil & avatat / refresh
- [ ] Utilisateur : CRUD limité surr
- [ ] Thèmes : CRUD + policies
- [ ] Partage : endpoints share / unshare / permissions
- [ ] Tâches : CRUD + archive + reorder
- [ ] Notifications in-app
- [ ] AuditLog + UserMetric + cron
- [ ] Tests unitaires + intégration
- [ ] OpenAPI + Postman
- [ ] CI (PHPStan, Pint, PHPUnit)
