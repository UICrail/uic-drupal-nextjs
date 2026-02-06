# 🐳 Dockerisation du projet Next-Drupal Starterkit

Ce guide explique comment utiliser Docker Compose pour faire fonctionner le projet Next-Drupal Starterkit.

## 📋 Prérequis

- Docker (version 20.10 ou supérieure)
- Docker Compose (version 2.0 ou supérieure)
- Git

## 🚀 Démarrage rapide

### Option 1 : Avec Makefile (recommandé)

```bash
# Cloner le projet
git clone https://github.com/wunderio/next-drupal-starterkit.git
cd next-drupal-starterkit

# Configuration de l'environnement
cp .env.example .env
# Éditez .env selon vos besoins

# Build et démarrage des services
make build
make up

# Configuration initiale
make setup

# Installer Drupal (première fois)
make drush ARGS="site:install --existing-config --account-name=admin --account-pass=admin"
```

### Option 2 : Avec Docker Compose directement

```bash
# Cloner le projet
git clone https://github.com/wunderio/next-drupal-starterkit.git
cd next-drupal-starterkit

# Configuration de l'environnement
cp .env.example .env
# Éditez .env selon vos besoins

# Démarrer tous les services
docker-compose up -d

# Installer les dépendances
docker-compose exec drupal-php composer install
docker-compose exec nextjs npm install

# Installer Drupal (première fois)
docker-compose exec drupal-php drush site:install --existing-config --account-name=admin --account-pass=admin
```

### Commandes Makefile disponibles

Exécutez `make help` pour voir toutes les commandes disponibles :

```bash
make help          # Affiche l'aide
make build         # Build les images
make up            # Démarre les services
make down          # Arrête les services
make logs          # Affiche les logs
make shell-drupal  # Shell dans Drupal
make shell-next    # Shell dans Next.js
make drush ARGS="cr"  # Exécute drush
make npm ARGS="run build"  # Exécute npm
```

### 5. Accéder aux services

- **Frontend Next.js**: http://localhost:3000
- **Backend Drupal**: http://localhost:8080
- **Proxy Nginx**: http://localhost:80
- **MailHog (emails)**: http://localhost:8025
- **Elasticsearch**: http://localhost:9200

## 🏗️ Architecture des services

### Services principaux

1. **mariadb**: Base de données MariaDB 10.11
2. **redis**: Cache Redis
3. **elasticsearch**: Moteur de recherche Elasticsearch 8.13.4
4. **drupal-php**: PHP-FPM 8.3 pour Drupal
5. **drupal-nginx**: Serveur web Nginx pour Drupal
6. **nextjs**: Application Next.js (Node.js 20)
7. **mailhog**: Serveur SMTP de test pour les emails
8. **nginx-proxy**: Reverse proxy Nginx

### Volumes persistants

- `mariadb_data`: Base de données
- `redis_data`: Cache Redis
- `elasticsearch_data`: Index Elasticsearch
- `drupal_files`: Fichiers uploadés Drupal
- `drupal_private`: Fichiers privés Drupal

## 🛠️ Commandes utiles

### Démarrer/Arrêter les services

```bash
# Démarrer tous les services
docker-compose up -d

# Arrêter tous les services
docker-compose down

# Arrêter et supprimer les volumes (⚠️ supprime les données)
docker-compose down -v

# Redémarrer un service spécifique
docker-compose restart nextjs
```

### Exécuter des commandes dans les conteneurs

```bash
# Drush dans Drupal
docker-compose exec drupal-php drush cr
docker-compose exec drupal-php drush status

# Composer dans Drupal
docker-compose exec drupal-php composer update

# NPM dans Next.js
docker-compose exec nextjs npm run build
docker-compose exec nextjs npm run graphql-codegen

# Shell dans un conteneur
docker-compose exec drupal-php sh
docker-compose exec nextjs sh
```

### Logs et debugging

```bash
# Logs en temps réel
docker-compose logs -f

# Logs d'un service spécifique
docker-compose logs -f nextjs

# Logs des 100 dernières lignes
docker-compose logs --tail=100 nextjs

# Voir l'état des services
docker-compose ps

# Voir l'utilisation des ressources
docker stats
```

## 🔧 Configuration avancée

### Mode développement

Pour le développement avec hot-reload, copiez `docker-compose.override.yml.example` :

```bash
cp docker-compose.override.yml.example docker-compose.override.yml
```

Le fichier `docker-compose.override.yml` est automatiquement chargé par Docker Compose et permet d'overrider les configurations pour le développement local.

### Variables d'environnement

Toutes les variables d'environnement sont définies dans `.env`. Les principales :

- **Database**: `MYSQL_*` pour la configuration MariaDB
- **Drupal**: `DRUPAL_*` pour les secrets et URLs
- **Next.js**: `NEXT_*` et `AUTH_*` pour la configuration Next.js
- **Redis**: `REDIS_PASS` pour le mot de passe Redis
- **Elasticsearch**: `ES_VERSION` pour la version ES

### Ports personnalisés

Pour changer les ports, modifiez les variables dans `.env` :

```env
DRUPAL_PORT=8080
NEXTJS_PORT=3000
MARIADB_PORT=3306
REDIS_PORT=6379
```

## 🏭 Build des images

### Build manuel

```bash
# Build toutes les images
docker-compose build

# Build une image spécifique
docker-compose build nextjs
docker-compose build drupal-php
```

### Build pour la production

Pour la production, modifiez `docker-compose.yml` pour utiliser le stage `production` :

```yaml
nextjs:
  build:
    target: production  # Au lieu de development
```

Puis build :

```bash
docker-compose build nextjs
docker-compose up -d
```

## 📦 Volumes et données

### Sauvegarder les données

```bash
# Sauvegarder la base de données
docker-compose exec mariadb mysqldump -u drupal -pdrupal drupal > backup.sql

# Sauvegarder les fichiers Drupal
docker cp next-drupal-mariadb:/var/www/html/web/sites/default/files ./backup/files
```

### Restaurer les données

```bash
# Restaurer la base de données
docker-compose exec -T mariadb mysql -u drupal -pdrupal drupal < backup.sql
```

## 🔍 Troubleshooting

### Service ne démarre pas

```bash
# Vérifier les logs
docker-compose logs service-name

# Vérifier l'état des services
docker-compose ps

# Vérifier la santé des services
docker-compose ps --format json | jq '.[].Health'
```

### Erreur de connexion à la base de données

```bash
# Vérifier que MariaDB est démarré
docker-compose ps mariadb

# Tester la connexion
docker-compose exec drupal-php php -r "var_dump(mysqli_connect('mariadb', 'drupal', 'drupal', 'drupal'));"
```

### Erreur de permissions

```bash
# Fixer les permissions Drupal
docker-compose exec drupal-php chown -R www-data:www-data /var/www/html/web/sites/default/files
docker-compose exec drupal-php chmod -R 755 /var/www/html/web/sites/default/files
```

### Rebuild complet

```bash
# Arrêter et supprimer tout
docker-compose down -v

# Rebuild les images
docker-compose build --no-cache

# Redémarrer
docker-compose up -d
```

## 🚢 Production

Pour la production, plusieurs optimisations sont recommandées :

1. **Utiliser le stage `production`** dans les Dockerfiles
2. **Configurer HTTPS** avec un certificat SSL
3. **Utiliser des secrets** au lieu de variables d'environnement en clair
4. **Configurer un reverse proxy** (Traefik, Nginx avec SSL)
5. **Sauvegardes automatiques** de la base de données
6. **Monitoring** avec des outils comme Prometheus/Grafana

### Exemple avec Traefik

Voir la documentation officielle de Traefik pour configurer un reverse proxy avec SSL automatique.

## 📚 Ressources

- [Documentation Docker Compose](https://docs.docker.com/compose/)
- [Next.js Docker Documentation](https://nextjs.org/docs/deployment#docker-image)
- [Drupal Docker Documentation](https://www.drupal.org/docs/develop/local-server-setup/docker-development-environments)

## 🆘 Support

Pour toute question ou problème, consultez :
- Le [README principal](../README.md)
- Les [issues GitHub](https://github.com/wunderio/next-drupal-starterkit/issues)

