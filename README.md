# Application de Gestion des Stagiaires (PFE)

Projet de fin d'etudes (PFE) realise par Saad Chaoui et Soumia EL-MZABI (FST-Fes) lors d'un stage chez Alten Maroc.

## Contexte

L'entreprise souhaite mettre en place une application web afin d'ameliorer la gestion des stagiaires, leur affectation ainsi que la communication entre les differents acteurs (RH, encadrants, stagiaires).

Problemes actuels :

- Gestion manuelle des stagiaires
- Difficulte de suivi
- Absence d'une communication centralisee

## Objectifs

- Automatiser la gestion des stagiaires
- Simplifier l'affectation des stages et des encadrants
- Assurer un suivi efficace des stagiaires
- Mettre a disposition un espace de communication

## Acteurs

- Administrateur
- Responsable de competence
- Encadrant
- Stagiaire

## Besoins fonctionnels

Gestion des stagiaires :

- Ajouter, modifier et supprimer un stagiaire
- Consulter la liste des stagiaires
- Archiver les stagiaires

Gestion des stages :

- Creer un stage
- Affecter un stagiaire a un stage
- Affecter un encadrant

Gestion des utilisateurs :

- Creer des comptes utilisateurs
- Gerer les roles
- Modifier les mots de passe

Suivi et communication :

- Messagerie interne
- Gestion des taches
- Suivi des absences

Autres fonctionnalites :

- Generation automatique d'attestations
- Gestion des demandes (prolongation, attestation, etc.)

## Besoins techniques

- Frontend : HTML, CSS, Bootstrap, JavaScript, jQuery
- Backend : PHP (Laravel)
- Base de donnees : MySQL
- AJAX pour les interactions dynamiques
- Architecture : MVC
- Versioning : Git

## User stories (resume)

Administrateur :

- Gerer les comptes utilisateurs
- Affecter les encadrants
- Consulter les demandes
- Archiver les stagiaires

Responsable de competence :

- Ajouter des stagiaires
- Affecter un stage
- Affecter un encadrant
- Suivre les absences
- Gerer les informations liees aux stages
- Assurer le suivi administratif

Encadrant :

- Attribuer des taches
- Suivre les stagiaires
- Valider la fin du stage

Stagiaire :

- Consulter les taches
- Envoyer des demandes
- Consulter les informations

## Lancer le projet en local

### Prerequis

- PHP >= 8.3 (avec les extensions usuelles Laravel : `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- Composer 2.x
- Node.js >= 18 et npm
- MySQL 8.x (ou MariaDB)

### 1) Recuperer les dependances

```bash
composer install
npm install
```

### 2) Creer la base de donnees

Le projet utilise par defaut une base MySQL nommee `stagiaires`. Creez-la :

```bash
mysql -u root -p -e "CREATE DATABASE stagiaires CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3) Configurer l'environnement

Creez le fichier `.env` (a partir de `.env.example` s'il existe, sinon a la main) puis renseignez les variables de base de donnees :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stagiaires
DB_USERNAME=root
DB_PASSWORD=
```

Generez ensuite la cle d'application :

```bash
php artisan key:generate
```

### 4) Migrer et initialiser les donnees

```bash
php artisan migrate --seed
```

### 5) Demarrer l'application

Option A - tout en une commande (serveur PHP + queue + logs + Vite) :

```bash
composer run dev
```

Option B - manuellement, dans deux terminaux :

```bash
# Terminal 1 : serveur Laravel
php artisan serve

# Terminal 2 : assets front (Tailwind / Vite) en mode dev
npm run dev
```

L'application est alors disponible sur http://127.0.0.1:8000.

Pour une build de production des assets : `npm run build`.

> Astuce : `composer run setup` enchaine automatiquement l'installation des dependances, la creation du `.env`, la generation de la cle, les migrations et la build des assets.

## Comptes de demonstration

Les seeders creent des comptes de demo. Exemple :

- admin@internships.local / password123

## Dossier important

- `routes/web.php` : definition des routes
- `app/Http/Controllers` : logique metier
- `app/Models` : modele Eloquent
- `resources/views` : vues Blade
- `database/migrations` : schema
- `database/seeders` : donnees de demo
