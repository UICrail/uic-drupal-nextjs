#!/bin/bash

# Script pour créer tous les fichiers Docker nécessaires
# Usage: bash create-docker-files.sh

set -e

echo "🐳 Création des fichiers Docker pour Next-Drupal Starterkit..."

# Créer les répertoires
mkdir -p docker/drupal docker/next docker/nginx

# Vérifier et créer les fichiers manquants
echo "✅ Vérification des fichiers..."

# Vérifier docker-compose.yml
if [ ! -f "docker-compose.yml" ]; then
    echo "⚠️  docker-compose.yml manquant - veuillez créer ce fichier"
else
    echo "✅ docker-compose.yml existe"
fi

# Vérifier les Dockerfiles
if [ ! -f "docker/drupal/Dockerfile" ]; then
    echo "⚠️  docker/drupal/Dockerfile manquant"
else
    echo "✅ docker/drupal/Dockerfile existe"
fi

if [ ! -f "docker/next/Dockerfile" ]; then
    echo "⚠️  docker/next/Dockerfile manquant"
else
    echo "✅ docker/next/Dockerfile existe"
fi

# Vérifier les configurations Nginx
if [ ! -f "docker/nginx/drupal.conf" ]; then
    echo "⚠️  docker/nginx/drupal.conf manquant"
else
    echo "✅ docker/nginx/drupal.conf existe"
fi

if [ ! -f "docker/nginx/proxy.conf" ]; then
    echo "⚠️  docker/nginx/proxy.conf manquant"
else
    echo "✅ docker/nginx/proxy.conf existe"
fi

# Vérifier le Makefile
if [ ! -f "Makefile" ]; then
    echo "⚠️  Makefile manquant"
else
    echo "✅ Makefile existe"
fi

# Vérifier README.docker.md
if [ ! -f "README.docker.md" ]; then
    echo "⚠️  README.docker.md manquant"
else
    echo "✅ README.docker.md existe"
fi

# Vérifier .dockerignore
if [ ! -f ".dockerignore" ]; then
    echo "⚠️  .dockerignore manquant"
else
    echo "✅ .dockerignore existe"
fi

echo ""
echo "📝 Pour créer .env.example, exécutez:"
echo "   cp .env.example .env  # Si le fichier existe déjà"
echo "   # Ou créez-le manuellement avec les variables d'environnement"
echo ""
echo "🎉 Vérification terminée!"
echo ""
echo "📚 Consultez README.docker.md pour plus d'informations"

