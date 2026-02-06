# UIC Configuration Module

Ce module fournit des types de contenu personnalisés et des configurations pour le projet UIC.

## 🚀 Installation

### Installation standard (recommandée)

Le module vérifie automatiquement l'existence des champs et configurations avant de les créer, évitant ainsi les conflits.

```bash
# Dans l'environnement DDEV
cd drupal/web/modules/custom/uic_config
./install.sh

# Ou manuellement avec DDEV
ddev drush pm:install uic_config -y
```

### Installation avec fichiers de configuration

Si vous voulez utiliser les fichiers de configuration YAML déplacés dans le dossier `config/optional/` :

```bash
# Installation avec import des fichiers de configuration
./install-with-config.sh
```

Cette méthode :
1. Installe le module
2. Importe les fichiers de configuration depuis `config/optional/`
3. Configure les champs et types de contenu

### Installation propre (si vous avez des conflits)

Si vous rencontrez des erreurs de configuration existante, utilisez l'installation propre :

```bash
# ATTENTION: Ceci supprime toutes les données existantes
./clean-install.sh
```

### Installation manuelle

```bash
# Activer le module
drush pm:install uic_config -y

# Vider les caches
drush cr
```

## 📋 Fonctionnalités

### Champs personnalisés pour Article

Le module ajoute les champs suivants au type de contenu Article existant :

- **field_subtitle** : Champ texte pour le sous-titre
- **field_header** : Zone de texte pour l'en-tête
- **field_footer** : Zone de texte pour le pied de page
- **field_gallery** : Bibliothèque média pour la galerie
- **field_attachments** : Bibliothèque média pour les pièces jointes
- **field_spip_id** : Champ texte pour l'ID SPIP
- **field_spip_url** : Champ texte pour l'URL SPIP

### Type de contenu Project Page

Un nouveau type de contenu "Project Page" avec des champs personnalisés.

## 🔧 Configuration

### Affichage des champs

Par défaut, les champs personnalisés sont :
- **Visibles** dans le formulaire d'édition
- **Masqués** dans l'affichage par défaut (pour éviter les conflits)

Pour afficher les champs dans les vues :
1. Aller dans Structure > Types de contenu > Article > Gérer l'affichage
2. Activer les champs souhaités dans les modes d'affichage appropriés

### Configuration des types de média

Pour les champs `field_gallery` et `field_attachments` :
1. Aller dans Structure > Types de média
2. Configurer les types de média autorisés dans les paramètres du champ

## 🌐 Exposition GraphQL

Le module expose automatiquement tous les champs personnalisés à GraphQL via GraphQL Compose.

### Champs exposés pour Article

```graphql
query {
  nodeQuery(limit: 10, filter: { conditions: [{ field: "type", value: "article" }] }) {
    entities {
      ... on Article {
        title
        fieldSubtitle
        fieldHeader
        fieldFooter
        fieldGallery {
          entities {
            ... on MediaImage {
              name
              fieldMediaImage {
                url
                alt
              }
            }
          }
        }
        fieldAttachments {
          entities {
            ... on MediaDocument {
              name
              fieldMediaDocument {
                url
                filename
              }
            }
          }
        }
        fieldSpipId
        fieldSpipUrl
      }
    }
  }
}
```

### Champs exposés pour Project Page

```graphql
query {
  nodeQuery(limit: 10, filter: { conditions: [{ field: "type", value: "project_page" }] }) {
    entities {
      ... on ProjectPage {
        title
        fieldSubtitle
        fieldHeader
        fieldFooter
        fieldImage {
          url
          alt
        }
        fieldStartEnd {
          value
        }
        fieldSpipId
        fieldSpipUrl
        fieldTags {
          entities {
            ... on TaxonomyTermTags {
              name
            }
          }
        }
      }
    }
  }
}
```

### Configuration automatique

Lors de l'installation, le module :
1. Configure automatiquement GraphQL Compose pour exposer les champs
2. Active les types de contenu Article et Project Page dans GraphQL
3. Expose tous les champs personnalisés avec les bonnes relations
4. Vide le cache du schéma GraphQL

## 🧪 Tests

### Test d'installation générale

```bash
./test-installation.sh
```

### Test de l'exposition GraphQL

```bash
./test-graphql.sh
```

Ce script vérifie :
- L'installation du module
- L'existence des champs personnalisés
- La configuration des affichages
- L'exposition GraphQL des champs
- La fonctionnalité des requêtes GraphQL

## 🔄 Résolution des problèmes

### Erreur de configuration existante

Si vous rencontrez l'erreur :
```
Configuration objects (...) provided by uic_config already exist in active configuration
```

**Solution 1 - Installation standard (recommandée)** :
Le module a été modifié pour éviter ce problème en :
1. Vérifiant l'existence des champs avant de les créer
2. Utilisant un hook d'installation intelligent
3. Configurant seulement les éléments manquants

**Solution 2 - Installation avec configuration** :
```bash
./install-with-config.sh
```
Cette méthode utilise les fichiers de configuration déplacés dans `config/optional/`.

**Solution 3 - Installation propre** :
```bash
./clean-install.sh
```
⚠️ **ATTENTION** : Ceci supprime toutes les données existantes.

### Problèmes GraphQL

Si les champs ne sont pas exposés en GraphQL :
1. Vérifier que GraphQL Compose est activé
2. Vider les caches : `drush cr`
3. Vérifier la configuration : `drush config:get graphql_compose.settings`
4. Exécuter le test GraphQL : `./test-graphql.sh`

### Réinstallation

Pour réinstaller le module :

```bash
# Désinstaller
drush pm:uninstall uic_config -y

# Réinstaller
drush pm:install uic_config -y
```

## 📁 Structure des fichiers

```
uic_config/
├── config/
│   ├── install/              # Vide (fichiers déplacés)
│   └── optional/             # Fichiers de configuration YAML
├── examples/                 # Exemples de requêtes GraphQL
│   └── graphql-queries.md
├── uic_config.info.yml       # Informations du module
├── uic_config.module         # Hooks du module (installation des champs)
├── install.sh               # Script d'installation standard
├── install-with-config.sh   # Script d'installation avec config
├── clean-install.sh         # Script d'installation propre
├── test-installation.sh     # Script de test général
├── test-graphql.sh         # Script de test GraphQL
└── README.md               # Ce fichier
```

## 🤝 Contribution

Pour modifier les champs ou ajouter de nouvelles fonctionnalités :

1. Modifier les fichiers de configuration dans `config/optional/`
2. Mettre à jour le hook d'installation dans `uic_config.module`
3. Mettre à jour la configuration GraphQL si nécessaire
4. Tester avec `./test-installation.sh` et `./test-graphql.sh`
5. Documenter les changements dans ce README
