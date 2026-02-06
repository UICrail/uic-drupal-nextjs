# UIC Configuration Module - Migration SPIP vers Drupal 10

Module Drupal 10 personnalise pour automatiser la migration d'un site **SPIP** (spip.net) vers **Drupal 10**, avec exposition des contenus via **GraphQL Compose** pour un frontend **Next.js**.

## Table des matieres

- [Vue d'ensemble](#vue-densemble)
- [Architecture de migration](#architecture-de-migration)
- [Installation](#installation)
- [Types de contenu et correspondance SPIP](#types-de-contenu-et-correspondance-spip)
- [Champs personnalises](#champs-personnalises)
- [Configuration des affichages](#configuration-des-affichages)
- [Exposition GraphQL](#exposition-graphql)
- [Scripts utilitaires](#scripts-utilitaires)
- [Hooks de mise a jour](#hooks-de-mise-a-jour)
- [Resolution des problemes](#resolution-des-problemes)
- [Structure des fichiers](#structure-des-fichiers)
- [Dependances](#dependances)
- [Contribution](#contribution)

---

## Vue d'ensemble

### Objectif

Ce module a ete concu pour faciliter la migration d'un site SPIP vers Drupal 10 dans le cadre du projet UIC. Il assure :

1. **Creation automatique des types de contenu** correspondant aux structures SPIP (articles, rubriques, projets)
2. **Tracabilite de la migration** via les champs `field_spip_id` et `field_spip_url` sur chaque contenu migre
3. **Exposition GraphQL** de tous les contenus pour un frontend Next.js decouples (headless)
4. **Idempotence** : toutes les operations verifient l'existence des elements avant creation, permettant des reinstallations sans risque

### Pile technique

| Couche | Technologie |
|--------|------------|
| CMS source | SPIP (spip.net) |
| CMS cible | Drupal 10 |
| API | GraphQL via GraphQL Compose |
| Frontend | Next.js |
| Migration | Drupal Migrate API + migrate_plus |
| Environnement local | DDEV ou Lando |

---

## Architecture de migration

### Correspondance des contenus SPIP vers Drupal

Le module mappe les structures de contenu SPIP vers des types Drupal de la maniere suivante :

| Contenu SPIP | Type Drupal | Machine name | Cree par |
|--------------|-------------|-------------|----------|
| Articles SPIP | Article | `article` | Drupal core (enrichi par le module) |
| Rubriques / Activites | Activity Page | `activity_page` | Le module (hook_install) |
| Projets | Project Page | `project_page` | Le module (hook_install) |

### Champs de tracabilite SPIP

Chaque type de contenu dispose de deux champs de tracabilite qui permettent de retrouver le contenu original dans SPIP :

| Champ Drupal | Type | Description |
|--------------|------|-------------|
| `field_spip_id` | Texte (255 car.) | Identifiant unique de l'objet dans la base SPIP (ex: `id_article`, `id_rubrique`) |
| `field_spip_url` | Texte (2048 car.) | URL originale du contenu sur le site SPIP |

Ces champs sont presents sur les trois types de contenu et sont essentiels pour :
- Verifier la completude de la migration
- Rediriger les anciennes URLs SPIP vers les nouvelles URLs Drupal
- Mettre a jour les contenus migres (migration incrementale)

### Configurations de migration (migrate_plus)

Le module fonctionne avec des configurations de migration Drupal Migrate API (module `migrate_plus`) qui peuvent etre nettoyees via le script `clean-and-install.sh` :

| Configuration | Description |
|---------------|-------------|
| `spip_enews_articles` | Migration des articles SPIP (newsletters/articles) |
| `spip_enews_articles_auto_paginate` | Migration avec pagination automatique |
| `spip_enews_articles_update` | Mise a jour incrementale des articles |
| `spip_project_pages` | Migration des pages de projet depuis SPIP |
| `spip_project_pages_local` | Migration locale des pages de projet |
| `spip_rubriques` | Migration des rubriques SPIP (categories/sections) |
| `spip_import` (group) | Groupe de migration regroupant toutes les migrations SPIP |

### Conversion du contenu SPIP

Les contenus SPIP utilisent un systeme de raccourcis typographiques propre (ex: `{italique}`, `{{gras}}`, `[lien->url]`). Lors de la migration, ces raccourcis doivent etre convertis en HTML standard. Le fichier `SPIP_shortcuts.md` documente l'ensemble de ces raccourcis pour reference.

Principaux raccourcis SPIP a convertir :

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `{texte}` | `<em>texte</em>` (italique) |
| `{{texte}}` | `<strong>texte</strong>` (gras) |
| `{{{titre}}}` | `<h3>titre</h3>` (intertitre) |
| `[texte->url]` | `<a href="url">texte</a>` (lien) |
| `[texte->artN]` | Lien interne vers l'article N |
| `[texte->rubN]` | Lien interne vers la rubrique N |
| `[[note]]` | Note de bas de page |
| `<quote>texte</quote>` | `<blockquote>texte</blockquote>` |

Voir le fichier `SPIP_shortcuts.md` pour la reference complete.

---

## Installation

### Prerequis

- Drupal 10 (ou 9)
- Les modules Drupal suivants actives : `node`, `field`, `field_ui`, `text`, `datetime`, `media`, `media_library`, `content_moderation`, `path`, `menu_ui`, `filter`, `image`, `taxonomy`
- Le module contrib `graphql_compose`
- Un environnement DDEV ou Lando (recommande)

### Installation standard (recommandee)

Le module verifie automatiquement l'existence des champs et configurations avant de les creer, evitant ainsi les conflits.

```bash
# Dans l'environnement DDEV
cd drupal/web/modules/custom/uic_config
./install.sh

# Ou manuellement avec DDEV
ddev drush pm:install uic_config -y
```

### Installation avec fichiers de configuration

Si vous voulez utiliser les fichiers de configuration YAML de sauvegarde :

```bash
./install-with-config.sh
```

### Installation propre (si vous avez des conflits)

Si vous rencontrez des erreurs de configuration existante, utilisez l'installation propre :

```bash
# ATTENTION: Ceci supprime toutes les donnees existantes
./clean-install.sh
```

### Installation propre complete (avec nettoyage des migrations SPIP)

Pour une reinstallation complete incluant le nettoyage des configurations de migration SPIP :

```bash
# ATTENTION: Supprime aussi les configurations de migration SPIP
./clean-and-install.sh
```

### Installation forcee

Pour forcer l'installation meme en cas d'erreurs :

```bash
./force-install.sh
# ou
./force-clean-install.sh
```

### Installation manuelle

```bash
# Activer le module
drush pm:install uic_config -y

# Vider les caches
drush cr
```

---

## Types de contenu et correspondance SPIP

### 1. Article (`article`) - Existant, enrichi

Le type de contenu Article existe par defaut dans Drupal 10. Le module l'enrichit avec des champs personnalises pour accueillir les articles migres depuis SPIP.

**Correspondance SPIP** : Articles SPIP (`spip_articles` / table `spip_articles`)

### 2. Activity Page (`activity_page`) - Cree par le module

Type de contenu pour les activites, correspondant aux rubriques SPIP qui decrivent des activites ou des sections thematiques du site.

**Correspondance SPIP** : Rubriques SPIP (`spip_rubriques` / table `spip_rubriques`)

**Description** : "Page type for activities (migrated from SPIP or created manually)."

### 3. Project Page (`project_page`) - Cree par le module

Type de contenu pour les projets, etudes de cas ou elements de portfolio.

**Correspondance SPIP** : Pages de projet SPIP

**Description** : "Use project pages for showcasing projects, case studies, or portfolio items."

---

## Champs personnalises

### Champs pour Article

| Champ | Machine name | Type | Description |
|-------|-------------|------|-------------|
| Sous-titre | `field_subtitle` | Texte (255 car.) | Sous-titre de l'article |
| Image mise en avant | `field_featured_image` | Reference Media (image) | Image principale |
| En-tete | `field_header` | Texte long (HTML) | Contenu d'en-tete |
| Pied de page | `field_footer` | Texte long (HTML) | Contenu de pied de page |
| Galerie | `field_gallery` | Reference Media (multiple) | Galerie d'images |
| Pieces jointes | `field_attachments` | Reference Media (multiple) | Documents et fichiers joints |
| Tags | `field_tags` | Reference Taxonomy | Tags (champ Drupal par defaut) |
| ID SPIP | `field_spip_id` | Texte (255 car.) | Identifiant dans la base SPIP |
| URL SPIP | `field_spip_url` | Texte (2048 car.) | URL originale sur le site SPIP |

**Champs desactives dans le formulaire** : `field_excerpt`, `field_image` (remplaces par les nouveaux champs personnalises).

### Champs pour Activity Page

| Champ | Machine name | Type | Description |
|-------|-------------|------|-------------|
| Corps | `body` | Texte avec resume | Contenu principal |
| Sous-titre | `field_subtitle` | Texte (255 car.) | Sous-titre |
| Image mise en avant | `field_featured_image` | Reference Media (image) | Image principale |
| En-tete | `field_header` | Texte long (HTML) | Contenu d'en-tete |
| Pied de page | `field_footer` | Texte long (HTML) | Contenu de pied de page |
| Galerie | `field_gallery` | Reference Media (multiple) | Galerie d'images |
| Pieces jointes | `field_attachments` | Reference Media (multiple) | Documents et fichiers joints |
| ID SPIP | `field_spip_id` | Texte (255 car.) | Identifiant dans la base SPIP |
| URL SPIP | `field_spip_url` | Texte (2048 car.) | URL originale sur le site SPIP |

### Champs pour Project Page

| Champ | Machine name | Type | Description |
|-------|-------------|------|-------------|
| Corps | `body` | Texte avec resume | Contenu principal |
| Sous-titre | `field_subtitle` | Texte (255 car.) | Sous-titre |
| Image mise en avant | `field_featured_image` | Reference Media (image) | Image principale |
| Image | `field_image` | Image (fichier) | Image du projet (upload direct) |
| En-tete | `field_header` | Texte long (HTML) | Contenu d'en-tete |
| Pied de page | `field_footer` | Texte long (HTML) | Contenu de pied de page |
| Dates debut/fin | `field_start_end` | Plage de dates | Periode du projet |
| Tags | `field_tags` | Reference Taxonomy (multiple) | Tags avec auto-creation |
| ID SPIP | `field_spip_id` | Texte (255 car.) | Identifiant dans la base SPIP |
| URL SPIP | `field_spip_url` | Texte (2048 car.) | URL originale sur le site SPIP |

### Recapitulatif des champs partages

| Champ | Article | Activity Page | Project Page |
|-------|---------|---------------|--------------|
| `body` | (core) | Oui | Oui |
| `field_subtitle` | Oui | Oui | Oui |
| `field_featured_image` | Oui | Oui | Oui |
| `field_header` | Oui | Oui | Oui |
| `field_footer` | Oui | Oui | Oui |
| `field_gallery` | Oui | Oui | - |
| `field_attachments` | Oui | Oui | - |
| `field_image` | - | - | Oui |
| `field_start_end` | - | - | Oui |
| `field_tags` | Oui (core) | - | Oui |
| `field_spip_id` | Oui | Oui | Oui |
| `field_spip_url` | Oui | Oui | Oui |

> **Note** : Les field storages sont partagees entre les types de contenu (ex: un seul `field.storage.node.field_subtitle` pour les trois bundles). Le module gere cela de maniere idempotente.

---

## Configuration des affichages

### Formulaires d'edition

Tous les champs personnalises sont **visibles** dans les formulaires d'edition avec les widgets suivants :

| Widget | Utilise pour |
|--------|-------------|
| `string_textfield` | `field_subtitle`, `field_spip_id`, `field_spip_url` |
| `text_textarea` | `field_header`, `field_footer` |
| `text_textarea_with_summary` | `body` |
| `media_library_widget` | `field_featured_image`, `field_gallery`, `field_attachments` |
| `image_image` | `field_image` (Project Page) |
| `daterange_default` | `field_start_end` (Project Page) |
| `entity_reference_autocomplete_tags` | `field_tags` |

Les champs SPIP (`field_spip_id`, `field_spip_url`) sont places en fin de formulaire (weight 50-51) pour ne pas encombrer l'edition quotidienne.

### Affichage par defaut

Par defaut, les champs personnalises sont **masques** dans l'affichage par defaut (mode view). C'est intentionnel pour un usage headless/GraphQL : le rendu est gere par le frontend Next.js.

Pour afficher les champs dans les modes d'affichage Drupal :
1. Aller dans **Structure > Types de contenu > [Type] > Gerer l'affichage**
2. Activer les champs souhaites dans les modes d'affichage appropries

---

## Exposition GraphQL

Le module expose automatiquement tous les champs personnalises a GraphQL via **GraphQL Compose**.

### Types de contenu exposes

| Type Drupal | Type GraphQL | Query de liste | Query unitaire |
|-------------|-------------|----------------|----------------|
| Article | `NodeArticle` | `nodeArticles` | `node(id: $id)` |
| Activity Page | `NodeActivityPage` | `nodeActivityPages` | `node(id: $id)` |
| Project Page | `NodeProjectPage` | `nodeProjectPages` | `node(id: $id)` |

### Configuration automatique

Lors de l'installation, le module configure GraphQL Compose pour :
1. Activer les trois types de contenu (`enabled`, `query_load_enabled`, `edges_enabled`, `routes_enabled`)
2. Exposer tous les champs personnalises pour chaque type
3. Invalider le cache du schema GraphQL

### Exemple de requete pour Article

```graphql
query GetArticles {
  nodeArticles(first: 10) {
    nodes {
      id
      title
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          name
          mediaImage {
            url
            alt
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          name
          mediaDocument {
            url
          }
        }
      }
      fieldTags {
        name
      }
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

### Exemple de requete pour Activity Page

```graphql
query GetActivityPages {
  nodeActivityPages(first: 10) {
    nodes {
      id
      title
      body {
        processed
      }
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          mediaDocument {
            url
          }
        }
      }
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

### Exemple de requete pour Project Page

```graphql
query GetProjectPages {
  nodeProjectPages(first: 10) {
    nodes {
      id
      title
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      body {
        processed
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldImage {
        url
        alt
      }
      fieldStartEnd {
        value
        end {
          value
        }
      }
      fieldSpipId
      fieldSpipUrl
      fieldTags {
        name
      }
    }
  }
}
```

### Requete utile pour la migration : retrouver les contenus SPIP

```graphql
query GetMigratedContent {
  articles: nodeArticles(first: 100) {
    nodes {
      id
      title
      fieldSpipId
      fieldSpipUrl
    }
  }
  activities: nodeActivityPages(first: 100) {
    nodes {
      id
      title
      fieldSpipId
      fieldSpipUrl
    }
  }
  projects: nodeProjectPages(first: 100) {
    nodes {
      id
      title
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

> Pour des exemples plus avances (pagination, fragments, integration Next.js), voir `examples/graphql-queries.md`.

---

## Scripts utilitaires

Le module fournit plusieurs scripts shell pour faciliter l'installation, le test et le diagnostic. Tous sont compatibles DDEV et Lando.

| Script | Description | Destructif ? |
|--------|-------------|:------------:|
| `install.sh` | Installation standard avec verifications pre/post | Non |
| `install-with-config.sh` | Installation avec import des fichiers YAML de backup | Non |
| `clean-install.sh` | Supprime les configs existantes puis reinstalle | **Oui** |
| `clean-and-install.sh` | Nettoyage complet (configs + migrations SPIP) puis reinstallation | **Oui** |
| `force-install.sh` | Desinstalle puis reinstalle le module | Oui |
| `force-clean-install.sh` | Supprime tout et force la reinstallation | **Oui** |
| `copy-from-export.sh` | Copie les configs depuis l'export Drupal vers le module | Non |
| `test-installation.sh` | Verifie l'installation (module, champs, affichages) | Non |
| `test-graphql.sh` | Verifie l'exposition GraphQL des champs | Non |
| `fix-project-page.sh` | Diagnostic et correction du type Project Page | Non (reparation) |

### Utilisation typique pour une premiere installation

```bash
cd drupal/web/modules/custom/uic_config

# 1. Installer le module
./install.sh

# 2. Verifier l'installation
./test-installation.sh

# 3. Verifier l'exposition GraphQL
./test-graphql.sh
```

### Utilisation pour une reinstallation propre (incluant les migrations SPIP)

```bash
# Nettoyage complet + reinstallation
./clean-and-install.sh
```

### Diagnostic si le type Project Page est manquant

```bash
# Diagnostic interactif avec correction automatique
./fix-project-page.sh
```

Ce script effectue :
1. Verification du statut du module
2. Verification des types de contenu existants
3. Verification des hooks de mise a jour
4. Consultation des logs recents
5. Execution des hooks de mise a jour en attente
6. Creation forcee du type Project Page si absent
7. Creation des champs associes
8. Verification finale

---

## Hooks de mise a jour

Le module implemente plusieurs hooks de mise a jour pour les installations existantes :

| Hook | Version schema | Description |
|------|---------------|-------------|
| `uic_config_install()` | - | Installation initiale : cree les 3 types de contenu, tous les champs, les affichages et la config GraphQL |
| `uic_config_update_9001()` | 9001 | Ajoute le type Activity Page et ses champs (pour les sites deja installes) |
| `uic_config_update_9002()` | 9002 | Ajoute le type Project Page, ses champs et configure les affichages |
| `uic_config_update_9003()` | 9003 | Force la creation de Project Page si absent (correction d'un bug de creation) |

### Executer les mises a jour

```bash
# Via DDEV
ddev drush updatedb -y
ddev drush cr

# Via drush direct
drush updatedb -y
drush cr
```

---

## Resolution des problemes

### Erreur de configuration existante

Si vous rencontrez l'erreur :
```
Configuration objects (...) provided by uic_config already exist in active configuration
```

**Solution 1 - Installation standard (recommandee)** :
Le module a ete concu pour eviter ce probleme en :
1. Verifiant l'existence des champs avant de les creer (`_uic_config_ensure_field_storage`, `_uic_config_ensure_field_instance`)
2. Utilisant un hook d'installation intelligent
3. Configurant seulement les elements manquants

**Solution 2 - Installation forcee** :
```bash
./force-install.sh
```

**Solution 3 - Installation propre** :
```bash
./clean-install.sh
```
**ATTENTION** : Ceci supprime toutes les donnees existantes.

### Project Page manquant apres installation

Si le type Project Page n'a pas ete cree :

1. **Executer le diagnostic** :
```bash
./fix-project-page.sh
```

2. **Ou forcer la mise a jour** :
```bash
ddev drush updatedb -y
ddev drush cr
```

3. **Verifier le schema** :
```bash
ddev drush php:eval "\$schema = \Drupal::keyValue('system.schema')->get('uic_config'); echo \$schema;"
```
Si le schema est < 9003, les mises a jour n'ont pas ete executees.

### Erreur str_starts_with() avec null (path alias)

Si vous rencontrez l'erreur :
```
Deprecated function: str_starts_with(): Passing null to parameter #1 ($haystack) of type string is deprecated in AliasPathProcessor->processOutbound()
```

Cette erreur n'est **pas causee par le module** mais par des contenus existants qui ont des chemins (path) null.

**Solutions :**

1. **Identifier les contenus problematiques** :
```bash
drush sql:query "SELECT nid, title, type FROM node_field_data WHERE nid NOT IN (SELECT SUBSTRING(path, 7) FROM path_alias WHERE path LIKE '/node/%')"
```

2. **Regenerer les alias avec Pathauto** :
```bash
drush pathauto:aliases-generate all
```

3. **Vider les caches** :
```bash
drush cr
```

### Problemes GraphQL

Si les champs ne sont pas exposes en GraphQL :

1. Verifier que GraphQL Compose est active :
```bash
ddev drush pm:list | grep graphql_compose
```

2. Verifier la configuration :
```bash
ddev drush config:get graphql_compose.settings
```

3. Forcer la reconfiguration GraphQL :
```bash
ddev drush php:eval "_uic_config_configure_graphql_compose();"
ddev drush cr
```

4. Executer le test GraphQL :
```bash
./test-graphql.sh
```

### Problemes lies a la conversion des raccourcis SPIP

Si les contenus migres contiennent encore des raccourcis SPIP non convertis (ex: `{italique}`, `{{gras}}`, `[lien->url]`), consultez le fichier `SPIP_shortcuts.md` pour la reference complete des raccourcis et leur equivalent HTML.

### Reinstallation complete

Pour reinstaller le module de zero :

```bash
# Desinstaller
drush pm:uninstall uic_config -y

# Reinstaller
drush pm:install uic_config -y

# Vider les caches
drush cr
```

---

## Structure des fichiers

```
uic_config/
├── README.md                    # Ce fichier (documentation principale)
├── SPIP_shortcuts.md            # Reference des raccourcis typographiques SPIP
│
├── uic_config.info.yml          # Definition du module et dependances
├── uic_config.install           # Hooks d'installation et de mise a jour
│                                #   - hook_install() : installation initiale
│                                #   - hook_update_9001() : ajout Activity Page
│                                #   - hook_update_9002() : ajout Project Page
│                                #   - hook_update_9003() : correction Project Page
│                                #   - Fonctions de creation de champs (idempotentes)
│                                #   - Configuration des affichages
│                                #   - Configuration GraphQL Compose
├── uic_config.module            # Hooks du module
│                                #   - hook_help()
│                                #   - Configuration des affichages Article
│                                #   - Configuration GraphQL Compose (helper)
│
├── examples/                    # Exemples de requetes GraphQL
│   └── graphql-queries.md       #   - Requetes pour les 3 types de contenu
│                                #   - Fragments reutilisables
│                                #   - Integration Next.js (client, hooks, composants)
│
├── install.sh                   # Installation standard avec verifications
├── install-with-config.sh       # Installation avec import des YAML de backup
├── clean-install.sh             # Installation propre (supprime les configs)
├── clean-and-install.sh         # Nettoyage complet + reinstallation
│                                #   (inclut nettoyage des migrations SPIP)
├── force-install.sh             # Installation forcee
├── force-clean-install.sh       # Installation propre forcee
├── copy-from-export.sh          # Copie des configs depuis l'export Drupal
├── test-installation.sh         # Test de l'installation
├── test-graphql.sh              # Test de l'exposition GraphQL
├── fix-project-page.sh          # Diagnostic et correction de Project Page
│
└── __bkp__/                     # Sauvegarde des configurations YAML
    ├── node.type.project_page.yml
    ├── core.entity_form_display.node.project_page.default.yml
    ├── core.entity_view_display.node.project_page.default.yml
    ├── field.field.node.article.*.yml        # Configs des champs Article
    ├── field.field.node.project_page.*.yml   # Configs des champs Project Page
    ├── field.storage.node.*.yml              # Configs des field storages
    └── uic_config/                           # Sauvegarde du code source du module
```

---

## Dependances

### Modules Drupal core

| Module | Utilisation |
|--------|------------|
| `drupal:node` | Types de contenu |
| `drupal:field` | Champs personnalises |
| `drupal:field_ui` | Interface de gestion des champs |
| `drupal:user` | Gestion des utilisateurs |
| `drupal:text` | Champs texte long et texte avec resume |
| `drupal:datetime` | Champ daterange pour Project Page |
| `drupal:media` | Entites media (images, documents) |
| `drupal:media_library` | Widget de selection media |
| `drupal:content_moderation` | Workflow de publication |
| `drupal:path` | Alias d'URL |
| `drupal:menu_ui` | Interface des menus |
| `drupal:filter` | Filtres de texte |
| `drupal:image` | Champ image (Project Page) |
| `drupal:taxonomy` | Vocabulaire Tags |

### Module contrib

| Module | Utilisation |
|--------|------------|
| `graphql_compose:graphql_compose` | Exposition automatique des champs en GraphQL |

### Module de migration (optionnel)

| Module | Utilisation |
|--------|------------|
| `migrate_plus` | Configurations de migration SPIP (non requis par le .info.yml mais utilise pour la migration effective) |

---

## Fonctions utilitaires internes

### Fonctions idempotentes de creation

| Fonction | Description |
|----------|-------------|
| `_uic_config_ensure_field_storage()` | Cree un field storage s'il n'existe pas |
| `_uic_config_ensure_field_instance()` | Cree une instance de champ sur un bundle s'il n'existe pas |
| `_uic_config_create_activity_page_content_type()` | Cree le type Activity Page s'il n'existe pas |
| `_uic_config_create_project_page_content_type()` | Cree le type Project Page s'il n'existe pas |
| `_uic_config_create_article_fields()` | Cree tous les champs Article |
| `_uic_config_create_activity_page_fields()` | Cree tous les champs Activity Page |
| `_uic_config_create_project_page_fields()` | Cree tous les champs Project Page |

### Fonctions de configuration

| Fonction | Description |
|----------|-------------|
| `_uic_config_configure_article_displays()` | Configure les affichages (form/view) pour Article |
| `_uic_config_configure_activity_page_displays()` | Configure les affichages pour Activity Page |
| `_uic_config_configure_project_page_displays()` | Configure les affichages pour Project Page |
| `_uic_config_ensure_all_form_displays()` | Force la mise a jour de tous les form displays |
| `_uic_config_disable_article_fields()` | Desactive `field_excerpt` et `field_image` dans le formulaire Article |
| `_uic_config_configure_graphql_compose()` | Configure l'exposition GraphQL pour les 3 types |

---

## Contribution

Pour modifier les champs ou ajouter de nouvelles fonctionnalites :

1. Modifier les fonctions dans `uic_config.install` pour les champs
2. Mettre a jour `uic_config.module` pour les affichages et GraphQL
3. Sauvegarder les configurations YAML dans `__bkp__/` si necessaire
4. Tester avec `./test-installation.sh` et `./test-graphql.sh`
5. Mettre a jour la documentation de migration si les correspondances SPIP changent
6. Documenter les changements dans ce README
