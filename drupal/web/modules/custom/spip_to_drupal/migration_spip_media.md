# Migration des Médias SPIP vers Drupal

Guide complet pour migrer les médias d'un site SPIP vers Drupal, en tenant compte des différents modes d'intégration des médias.

## Analyse préalable

### Types de médias dans SPIP
- **Médias attachés** : stockés dans `spip_documents` avec liaisons via `spip_documents_liens`
- **Médias intégrés** : présents dans le contenu via des raccourcis SPIP (`<doc123>`, `<img456>`, etc.)
- **Localisation** : fichiers stockés dans le répertoire `IMG/` de SPIP

### Champs SPIP concernés
- `#TEXTE` : contenu principal des articles
- `#PS` : post-scriptum
- `#CHAPO` : chapeau/introduction
- `#DESCRIPTIF` : description

## Prérequis Drupal

### Modules nécessaires
```bash
# Modules core
drush en media media_library file

# Modules contrib recommandés
drush en migrate_plus migrate_tools migrate_upgrade
drush en entity_embed editor_advanced_link
drush en pathologic token
```

### Configuration des types de média
Créez les types de média Drupal correspondants :
- Image
- Document
- Vidéo
- Audio

## Étape 1 : Migration des fichiers physiques

### Script de copie des fichiers
```bash
#!/bin/bash
# Copie des fichiers médias SPIP vers Drupal
rsync -av /path/to/spip/IMG/ /path/to/drupal/sites/default/files/media/

# Ajustement des permissions
find /path/to/drupal/sites/default/files/media/ -type f -exec chmod 644 {} \;
find /path/to/drupal/sites/default/files/media/ -type d -exec chmod 755 {} \;
```

## Étape 2 : Configuration de la migration

### Fichier de migration des médias
Créez `config/install/migrate_plus.migration.spip_media.yml` :

```yaml
id: spip_media
label: 'Migration des médias SPIP'
migration_group: spip_migration
source:
  plugin: database
  database_state_key: spip_database
  query: |
    SELECT 
      d.id_document,
      d.fichier,
      d.titre,
      d.descriptif,
      d.extension,
      d.taille,
      d.largeur,
      d.hauteur,
      d.mode,
      d.distant
    FROM spip_documents d
    WHERE d.statut = 'publie'
  
process:
  # Détermination du type de média basé sur l'extension
  media_type:
    plugin: static_map
    source: extension
    map:
      jpg: image
      jpeg: image
      png: image
      gif: image
      pdf: document
      doc: document
      docx: document
      mp4: video
      avi: video
      mp3: audio
    default_value: document
  
  # Nom du média
  name: titre
  
  # Import du fichier
  field_media_file/target_id:
    plugin: file_import
    source: 
      - fichier
      - titre
    settings:
      file_function: copy
      destination: 'public://media/'
      move: false
      reuse: true
  
  # Champs supplémentaires
  field_media_file/description: descriptif
  field_media_file/alt: titre
  
  # Métadonnées
  field_media_width: largeur
  field_media_height: hauteur
  field_media_size: taille
  
  # UID utilisateur (admin par défaut)
  uid:
    plugin: default_value
    default_value: 1
  
  # Statut publié
  status:
    plugin: default_value
    default_value: 1

destination:
  plugin: 'entity:media'

dependencies:
  enforced:
    module:
      - mon_module_migration
```

### Table de correspondance des IDs
Créez une table pour mapper les anciens IDs SPIP aux nouveaux IDs Drupal :

```sql
CREATE TABLE spip_drupal_media_mapping (
  spip_id INT PRIMARY KEY,
  drupal_id INT,
  media_type VARCHAR(50),
  filename VARCHAR(255),
  migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Étape 3 : Traitement des médias intégrés

### Module personnalisé de transformation

Créez `modules/custom/spip_media_transformer/spip_media_transformer.module` :

```php
<?php

/**
 * @file
 * Module de transformation des raccourcis médias SPIP.
 */

/**
 * Transforme les raccourcis SPIP en références Drupal.
 */
function spip_media_transformer_transform_content($content) {
  // Transformation des documents <doc123|left>
  $content = preg_replace_callback(
    '/<doc(\d+)(\|([^>]*))?>/',
    'spip_media_transformer_replace_doc_tag',
    $content
  );
  
  // Transformation des images <img123|left>
  $content = preg_replace_callback(
    '/<img(\d+)(\|([^>]*))?>/',
    'spip_media_transformer_replace_img_tag',
    $content
  );
  
  // Transformation des embeddings <emb123|center>
  $content = preg_replace_callback(
    '/<emb(\d+)(\|([^>]*))?>/',
    'spip_media_transformer_replace_emb_tag',
    $content
  );
  
  return $content;
}

/**
 * Remplace les tags <doc> par des références Drupal.
 */
function spip_media_transformer_replace_doc_tag($matches) {
  $spip_id = $matches[1];
  $params = isset($matches[3]) ? $matches[3] : '';
  
  $drupal_media_id = spip_media_transformer_get_drupal_media_id($spip_id);
  
  if (!$drupal_media_id) {
    return '<!-- Media SPIP #' . $spip_id . ' non trouvé -->';
  }
  
  // Parse des paramètres SPIP (left, right, center)
  $alignment = spip_media_transformer_parse_alignment($params);
  $caption = spip_media_transformer_parse_caption($params);
  
  // Génère le token Entity Embed
  $embed_code = '<drupal-entity data-entity-type="media" data-entity-uuid="' . 
                spip_media_transformer_get_media_uuid($drupal_media_id) . '"';
  
  if ($alignment) {
    $embed_code .= ' data-align="' . $alignment . '"';
  }
  
  if ($caption) {
    $embed_code .= ' data-caption="' . htmlspecialchars($caption) . '"';
  }
  
  $embed_code .= '></drupal-entity>';
  
  return $embed_code;
}

/**
 * Récupère l'ID Drupal correspondant à un ID SPIP.
 */
function spip_media_transformer_get_drupal_media_id($spip_id) {
  $database = \Drupal::database();
  
  $result = $database->select('spip_drupal_media_mapping', 'm')
    ->fields('m', ['drupal_id'])
    ->condition('spip_id', $spip_id)
    ->execute()
    ->fetchField();
  
  return $result;
}

/**
 * Parse l'alignement depuis les paramètres SPIP.
 */
function spip_media_transformer_parse_alignment($params) {
  if (strpos($params, 'left') !== FALSE) return 'left';
  if (strpos($params, 'right') !== FALSE) return 'right';
  if (strpos($params, 'center') !== FALSE) return 'center';
  return null;
}

/**
 * Parse la légende depuis les paramètres SPIP.
 */
function spip_media_transformer_parse_caption($params) {
  // Extraction d'une éventuelle légende après |
  $parts = explode('|', $params);
  foreach ($parts as $part) {
    $part = trim($part);
    if (!in_array($part, ['left', 'right', 'center']) && !empty($part)) {
      return $part;
    }
  }
  return null;
}
```

### Hook de transformation du contenu

```php
/**
 * Implements hook_migrate_process_info().
 */
function spip_media_transformer_migrate_process_info() {
  return [
    'spip_content_transform' => [
      'class' => '\Drupal\spip_media_transformer\Plugin\migrate\process\SpipContentTransform',
    ],
  ];
}
```

Créez le plugin `src/Plugin/migrate/process/SpipContentTransform.php` :

```php
<?php

namespace Drupal\spip_media_transformer\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Plugin de transformation du contenu SPIP.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_content_transform"
 * )
 */
class SpipContentTransform extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return $value;
    }
    
    return spip_media_transformer_transform_content($value);
  }

}
```

## Étape 4 : Migration des articles avec transformation

### Configuration de migration des articles
Modifiez votre migration d'articles pour inclure la transformation :

```yaml
# migrate_plus.migration.spip_articles.yml
process:
  title: titre
  
  # Transformation du contenu avec médias intégrés
  body/value:
    plugin: spip_content_transform
    source: texte
  
  body/format:
    plugin: default_value
    default_value: full_html
  
  # Transformation des autres champs
  field_chapo/value:
    plugin: spip_content_transform
    source: chapo
  
  field_ps/value:
    plugin: spip_content_transform
    source: ps
```

## Étape 5 : Exécution de la migration

### Commandes Drush

```bash
# 1. Import de la configuration de migration
drush config-import

# 2. Migration des médias d'abord
drush migrate:import spip_media --feedback=50

# 3. Vérification du statut
drush migrate:status

# 4. Migration des articles avec transformation des médias
drush migrate:import spip_articles --feedback=10

# 5. Vérification post-migration
drush migrate:messages spip_media
drush migrate:messages spip_articles
```

### Script de vérification

```php
<?php
/**
 * Script de vérification post-migration des médias.
 */

// Vérification des fichiers orphelins
$orphan_files = [];
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$file_storage = \Drupal::entityTypeManager()->getStorage('file');

$media_entities = $media_storage->loadMultiple();
foreach ($media_entities as $media) {
  $file_field = $media->get('field_media_file');
  if (!$file_field->isEmpty()) {
    $file = $file_field->entity;
    if ($file && !file_exists($file->getFileUri())) {
      $orphan_files[] = $file->getFilename();
    }
  }
}

if (!empty($orphan_files)) {
  echo "Fichiers manquants : " . implode(', ', $orphan_files) . "\n";
}

// Vérification des références médias dans le contenu
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $node_storage->loadByProperties(['type' => 'article']);

foreach ($nodes as $node) {
  $body = $node->get('body')->value;
  
  // Recherche de références médias non résolues
  if (preg_match_all('/<drupal-entity[^>]*data-entity-uuid="([^"]*)"/', $body, $matches)) {
    foreach ($matches[1] as $uuid) {
      $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $uuid);
      if (!$media) {
        echo "Média UUID non trouvé dans le nœud " . $node->id() . " : " . $uuid . "\n";
      }
    }
  }
  
  // Recherche de tags SPIP non transformés
  if (preg_match('/<(doc|img|emb)\d+/', $body)) {
    echo "Tags SPIP non transformés dans le nœud " . $node->id() . "\n";
  }
}
```

## Étape 6 : Optimisation et nettoyage

### Génération des styles d'image
```bash
# Régénération des styles d'image pour tous les médias
drush image-flush --all
drush image-derive
```

### Nettoyage des URLs
Activez et configurez le module Pathologic pour corriger les liens internes.

### Indexation Search API
```bash
# Réindexation si vous utilisez Search API
drush search-api:reset-tracker
drush search-api:index
```

## Dépannage

### Problèmes courants

1. **Fichiers non trouvés** : Vérifiez les chemins et permissions
2. **Types MIME incorrects** : Ajustez la configuration des types de fichiers Drupal
3. **Médias dupliqués** : Utilisez l'option `reuse: true` dans la configuration
4. **Performance** : Exécutez la migration par lots avec `--limit`

### Rollback en cas de problème
```bash
# Annulation de la migration
drush migrate:rollback spip_articles
drush migrate:rollback spip_media

# Nettoyage complet
drush migrate:reset-status spip_media
drush migrate:reset-status spip_articles
```

## Validation finale

### Checklist de vérification
- [ ] Tous les fichiers médias sont présents
- [ ] Les références dans le contenu fonctionnent
- [ ] Les styles d'image se génèrent correctement  
- [ ] Pas de tags SPIP non transformés dans le contenu
- [ ] Les permissions sur les fichiers sont correctes
- [ ] La recherche indexe correctement les nouveaux contenus

Cette approche garantit une migration complète et fiable des médias SPIP vers Drupal, en préservant l'intégrité du contenu et les fonctionnalités d'affichage.