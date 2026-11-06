# Mentor — Application de réservation de formations

Mentor est une application web développée avec Symfony permettant de consulter des formations et de gérer les formateurs, les disponibilités et les réservations.

## Sommaire

- [Prérequis](#prérequis)
- [Technologies](#technologies)
- [Installation avec Docker](#installation-avec-docker)
- [Configuration](#configuration)
- [Accéder à l’application](#accéder-à-lapplication)
- [Commandes utiles](#commandes-utiles)
- [Base de données](#base-de-données)
- [Dépannage](#dépannage)

## Prérequis

Installer les outils suivants :

- [Git](https://git-scm.com/)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Docker Compose, inclus dans Docker Desktop

Vérifier les installations dans PowerShell :

```powershell
git --version
docker --version
docker compose version
```

Démarrer Docker Desktop avant de lancer les commandes Docker.

## Technologies

- **Backend :** PHP 8.4 et Symfony 7.4
- **Base de données :** MySQL 8.0
- **Serveur web :** Nginx
- **Environnement :** Docker Compose
- **Moteur de templates :** Twig

## Installation avec Docker

### 1. Récupérer le projet

Si le dépôt est déjà présent sur votre ordinateur, passez à l’étape 2.

```powershell
git clone <URL_DU_DEPOT>
cd Mentor
```

Remplacez `<URL_DU_DEPOT>` par l’URL réelle du dépôt Git.

### 2. Vérifier les fichiers de configuration

À la racine du projet, vérifier la présence notamment de :

- `compose.yaml` ou `docker-compose.yml`
- `Dockerfile`
- la configuration Nginx utilisée par le projet
- `composer.json`

Les noms exacts des fichiers peuvent varier selon la configuration du dépôt.

### 3. Construire et démarrer les conteneurs

Depuis le dossier `Mentor` :

```powershell
docker compose up -d --build
```

Vérifier leur état :

```powershell
docker compose ps
```

Les services de l’application, de la base de données et de Nginx doivent être démarrés.

### 4. Installer les dépendances PHP

Si les dépendances Composer ne sont pas déjà installées dans l’image ou le volume du projet :

```powershell
docker compose exec app composer install
```

### 5. Vérifier la configuration Symfony

```powershell
docker compose exec app php bin/console about
```

Si le projet utilise les migrations Doctrine, **ne les exécutez pas automatiquement** sur une base contenant déjà des données. Vérifiez d’abord l’état du schéma et des migrations.

## Configuration

La configuration Docker actuelle de la base de données utilise les paramètres suivants à l’intérieur du réseau Docker :

| Paramètre | Valeur |
|---|---|
| Hôte MySQL | `database` |
| Port MySQL interne | `3306` |
| Base de données | `mentor` |
| Utilisateur | `mentor` |

L’URL de connexion Symfony doit être définie dans l’environnement du conteneur, généralement via `DATABASE_URL` :

```dotenv
DATABASE_URL="mysql://mentor:mentor_dev_password@database:3306/mentor?serverVersion=8.0.0&charset=utf8mb4"
```

Adaptez cette valeur à votre fichier Compose et à votre configuration locale. **Ne publiez pas de vrais mots de passe dans le dépôt Git.** Pour un environnement de production, utilisez des secrets et des identifiants distincts.

## Accéder à l’application

Une fois les conteneurs démarrés, ouvrir :

**http://localhost:8080**

Le port dépend de la configuration du service Nginx dans le fichier Compose.

## Commandes utiles

### Voir l’état des conteneurs

```powershell
docker compose ps
```

### Consulter les journaux

```powershell
docker compose logs --tail=100 app nginx
docker compose logs --tail=100 database
```

### Ouvrir un terminal dans le conteneur PHP

```powershell
docker compose exec app sh
```

### Exécuter une commande Symfony

```powershell
docker compose exec app php bin/console
```

Exemple pour vider le cache :

```powershell
docker compose exec app php bin/console cache:clear
```

### Arrêter les conteneurs

```powershell
docker compose stop
```

Pour arrêter et retirer les conteneurs sans supprimer les volumes :

```powershell
docker compose down
```

**Ne pas ajouter `-v` à cette commande** si vous souhaitez conserver les données de la base Docker.

## Base de données

La base MySQL est exécutée dans le conteneur Docker `database`. Les données sont conservées par le volume défini dans le fichier Compose.

### Vérifier la connexion à la base

```powershell
docker compose exec database mysql -umentor -p mentor
```

La commande demande le mot de passe de l’utilisateur MySQL. Une fois connecté, vous pouvez exécuter :

```sql
SHOW TABLES;
SELECT COUNT(*) FROM formation;
SELECT COUNT(*) FROM reservation;
```

Pour quitter MySQL :

```sql
EXIT;
```

### Sauvegarde

Avant toute modification importante du schéma ou des données, effectuer une sauvegarde. Exemple, depuis PowerShell :

```powershell
docker compose exec -T database mysqldump -umentor -pmentor_dev_password mentor > backup_docker.sql
```

Cette commande est adaptée à la configuration locale actuelle. Le mot de passe passé en argument peut générer un avertissement ; pour un usage plus sûr, utilisez une méthode de gestion des secrets.

Ne supprimez pas les volumes Docker pour résoudre un problème de migration ou de schéma.

## Dépannage

### L’application ne répond pas

```powershell
docker compose ps
docker compose logs --tail=100 app nginx
```

Vérifiez que Docker Desktop est démarré et que le port `8080` n’est pas utilisé par un autre service.

### Erreur de connexion à MySQL

```powershell
docker compose logs --tail=100 database
docker compose exec app php bin/console about
```

Dans Docker, l’hôte de la base est généralement `database`, et non `localhost`.

### Erreur Symfony ou page 500

```powershell
docker compose logs --tail=100 app
docker compose exec app php bin/console cache:clear
```

Vérifiez aussi la configuration des variables d’environnement et les journaux Nginx.

### Problème de dépendances Composer

```powershell
docker compose exec app composer install
```

Si la commande échoue, examinez le message d’erreur avant de modifier les versions PHP ou Symfony.

---

## État de la configuration

Ce README décrit l’installation Docker locale connue du projet Mentor. Vérifiez les noms des services, les ports, le fichier Compose et les variables d’environnement dans le dépôt avant de l’utiliser sur une autre machine.
