# SPIP to Drupal Migration Module

Module Drupal 10 pour migrer le contenu d'un site SPIP (spip.net) vers Drupal, avec support des images, des raccourcis typographiques SPIP, de la pagination automatique et de l'import par fichier local.

## Fonctionnalites

- **Source XML personnalisee** (`spip_xml_file`) : parsing des exports XML SPIP avec gestion des namespaces
- **Pagination automatique** : import de gros volumes en mode batch avec auto-pagination
- **6 migrations** : 3 types de contenu (Articles, Activity Pages, Project Pages) x 2 modes (URL, fichier local)
- **Import par fichier local** : upload via formulaire ou chemin serveur, pour contourner CrowdSec/pare-feu
- **Conversion SPIP -> HTML** : raccourcis typographiques SPIP convertis automatiquement
- **Import d'images** : import automatique depuis les champs `logourl`, `images`, `portfolio`
- **Import de documents** : PDF, Word et autres documents integres dans le contenu
- **Interface d'administration** : formulaire web a `/admin/content/spip-migration` avec logs, progression et batch
- **Idempotence** : re-execution sans doublon grace a la migration map
- **Script de reinstallation** : `clean_install.sh` pour nettoyage complet et reinstallation sur serveur Debian

## Installation

1. Placer le module dans `web/modules/custom/spip_to_drupal/`
2. Activer les modules requis :
   ```bash
   drush pm:enable migrate migrate_plus migrate_tools spip_to_drupal
   ```
3. Le module `uic_config` doit etre installe (il cree les types de contenu et champs cibles)

### Reinstallation complete (script)

```bash
cd /chemin/vers/drupal/web/modules/custom/spip_to_drupal
sed -i 's/\r$//' clean_install.sh
chmod +x clean_install.sh
./clean_install.sh            # Nettoyage + reinstallation
./clean_install.sh --import   # Idem + lance toutes les migrations URL
```

## Migrations disponibles

### Import par URL (auto-pagination)

| Migration ID | Destination | URL source SPIP |
|-------------|-------------|-----------------|
| `spip_enews_articles` | Article (`article`) | `https://uic.org/com/?page=enews_export` |
| `spip_rubriques` | Activity Page (`activity_page`) | `https://uic.org/?page=rubriques_export` |
| `spip_project_pages` | Project Page (`project_page`) | `https://uic.org/?page=project_page_export` |

### Import par fichier local

| Migration ID | Destination | Fichier attendu |
|-------------|-------------|-----------------|
| `spip_enews_articles_local` | Article (`article`) | `public://feeds/enews_articles.xml` |
| `spip_rubriques_local` | Activity Page (`activity_page`) | `public://feeds/rubriques.xml` |
| `spip_project_pages_local` | Project Page (`project_page`) | `public://feeds/project_pages.xml` |

> Les migrations locales acceptent aussi un chemin serveur ou un fichier place dans le repertoire du module.

## Utilisation

### Interface d'administration (recommandee)

Aller sur `/admin/content/spip-migration` pour :
- **Source Type** : URL Import ou Local File Import
- **Destination content type** : Article, Activity Page, Project Page
- **Pour l'import local** :
  - Upload un fichier XML via le champ d'upload, OU
  - Specifier un chemin serveur dans "Server File Path" (ex: `project_page.xml` pour un fichier dans le repertoire du module, ou un chemin absolu)
- Lancer l'import (batch ou immediat)
- Suivre la progression et les logs en temps reel

### Import par fichier local (contournement CrowdSec)

Quand les requetes HTTP du serveur vers SPIP sont bloquees (CrowdSec, pare-feu) :

1. **Exporter le XML depuis SPIP** (dans un navigateur non bloque) :
   - Articles : `https://uic.org/com/?page=enews_export&par_page=9999`
   - Activity Pages : `https://uic.org/?page=rubriques_export&par_page=9999`
   - Project Pages : `https://uic.org/?page=project_page_export&par_page=9999`
   
   > `par_page=9999` pour exporter tout d'un coup au lieu de 10 items par defaut.

2. **Deposer le fichier XML** sur le serveur :
   - SCP/SFTP dans le repertoire du module, ou
   - Upload via le formulaire admin

3. **Lancer l'import** via l'interface admin :
   - Source Type: **Local File Import**
   - Destination: le type de contenu souhaite
   - Server File Path: nom du fichier (ex: `project_page.xml`)
   - Cliquer **Start Import (Immediate)**

### CLI - Import par URL

```bash
# Statut de toutes les migrations
drush migrate:status --group=spip_import

# Import articles avec auto-pagination
drush migrate:import spip_enews_articles

# Import project pages
drush migrate:import spip_project_pages

# Import rubriques
drush migrate:import spip_rubriques

# Import avec limite
drush migrate:import spip_enews_articles --limit=10

# Import d'un item specifique
drush migrate:import spip_enews_articles --idlist=art12044 --verbose

# Rollback
drush migrate:rollback spip_project_pages
```

### CLI - Import par fichier local

```bash
# Deposer le fichier XML dans le repertoire du module ou dans public://feeds/
# Puis lancer la migration locale
drush migrate:import spip_project_pages_local
drush migrate:import spip_enews_articles_local
drush migrate:import spip_rubriques_local
```

### CLI - Batch

```bash
# Obtenir le nombre de pages
drush spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20

# Migrer une page specifique
drush spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20

# Migrer toutes les pages
drush spip:migrate-all --migration-id=spip_enews_articles --per-page=20 --url=https://uic.org/com/?page=enews_export
```

## Types de contenu et champs mappes

### eNews Articles -> Article (`article`)

| Source SPIP | Champ Drupal | Traitement |
|-------------|-------------|------------|
| `titre` | `title` | Direct |
| `texte` | `body` | spip_raccourcis_to_html + spip_auto_link + spip_doc_to_embed + spip_html_transform + spip_html_media_embed |
| `soustitre` | `field_subtitle` | Direct |
| `formerurl` | `field_spip_url` | Direct |
| `id` | `field_spip_id` | Direct |
| `chapo` | `field_header` | Meme pipeline que body |
| `ps` | `field_footer` | Meme pipeline que body |
| `logourl` | `field_featured_image` | spip_logourl_to_media_id |
| `tags1` | `field_tags` | default_value + trim + explode(;) + entity_generate |
| `images` | `field_gallery` | spip_portfolio_to_media (media image) |
| `portfolio` | `field_attachments` | spip_portfolio_to_media (media document) |

### Project Pages -> Project Page (`project_page`)

| Source SPIP | Champ Drupal | Traitement |
|-------------|-------------|------------|
| `titre` | `title` | Direct |
| `texte` | `body` | spip_raccourcis_to_html + spip_auto_link + spip_doc_to_embed + spip_html_transform + spip_html_media_embed |
| `soustitre` | `field_subtitle` | Direct |
| `formerurl` | `field_spip_url` | Direct |
| `id` | `field_spip_id` | Direct |
| `chapo` | `field_header` | Meme pipeline que body |
| `ps` | `field_footer` | Meme pipeline que body |
| `logourl` | `field_featured_image` | spip_logourl_to_media_id |
| `tags1` | `field_tags` | default_value + trim + explode(;) + entity_generate |
| `images` | `field_gallery` | spip_portfolio_to_media (media image) |
| `portfolio` | `field_attachments` | spip_portfolio_to_media (media document) |

### Rubriques -> Activity Page (`activity_page`)

| Source SPIP | Champ Drupal | Traitement |
|-------------|-------------|------------|
| `titre` | `title` | Direct |
| `texte` | `body` | spip_raccourcis_to_html + spip_auto_link + spip_doc_to_embed + spip_html_transform + spip_html_media_embed |
| `soustitre` | `field_subtitle` | Direct |
| `id` | `field_spip_id` | Direct |
| `desc` | `field_header` | Meme pipeline que body |
| `logourl` | `field_featured_image` | spip_logourl_to_media_id |
| `group1` | `field_tags` | default_value + trim + explode(;) + entity_generate |
| `images` | `field_gallery` | spip_portfolio_to_media (media image) |
| `portfolio` | `field_attachments` | spip_portfolio_to_media (media document) |

## Plugins de traitement personnalises

| Plugin | Description |
|--------|-------------|
| `spip_raccourcis_to_html` | Convertit les raccourcis typographiques SPIP ({gras}, {/italique/}, liens, listes) en HTML |
| `spip_auto_link` | Detecte et convertit les URLs brutes en liens HTML |
| `spip_doc_to_embed` | Resout les references `<docXXX>` SPIP en embeds media Drupal, telecharge les fichiers |
| `spip_html_transform` | Nettoyage HTML (classes SPIP, balises obsoletes) |
| `spip_html_media_embed` | Importe les `<img>` en tant que media Drupal, corrige les URLs relatives |
| `spip_logourl_to_media_id` | Telecharge l'image logo et cree une entite Media Drupal |
| `spip_portfolio_to_media` | Importe les IDs portfolio/images SPIP en references media Drupal |

## Templates SPIP (exports XML)

Les templates SPIP generent le XML source. Ils sont dans le repertoire du module pour reference :

| Fichier | Endpoint SPIP | Contenu |
|---------|---------------|---------|
| `enews_export.html` | `/com/?page=enews_export` | Articles eNews (rubrique 7 du sous-site /com/) |
| `project_page_export.html` | `/?page=project_page_export` | Project Pages (secteur 99) |
| `rubriques_export.html` | `/?page=rubriques_export` | Rubriques/Activity Pages (secteurs 145, 13, 157, etc.) |

Tous supportent la pagination via `?num_page=X&par_page=Y` (defaut: page 1, 10 items).

## Configuration

### Source XML
- **URL Import** : URL SPIP avec auto-pagination
- **File Import** : chemin `public://feeds/`, chemin absolu, ou nom de fichier dans le repertoire du module
- XPath selector : `//rubrique` (articles, project pages), `//*[local-name()="rubrique"]` (rubriques)
- Namespace support : detection et enregistrement automatiques

### Tags (field_tags)
Le pipeline tags utilise un champ temporaire `_tags_raw` avec `default_value: ''` pour eviter les erreurs `NULL is not a string` quand le champ source est vide.

### Field Mappings
Editer les fichiers YAML dans `config/install/` pour personnaliser les mappings.

## Scripts

| Script | Usage |
|--------|-------|
| `clean_install.sh` | **Principal** - Nettoyage complet + reinstallation (compatible Debian sans DDEV) |
| `clean_install.sh --import` | Idem + lance les 3 migrations URL |
| `reset_migrations.sh` | Reset des migrations pour reimport |
| `index_spip_images.sh` | Indexation des images placees manuellement |

### clean_install.sh

Le script principal en 8 etapes :
1. Desinstallation du module
2. Suppression de TOUTES les configs de migration (actives + orphelines + SQL fallback)
3. Nettoyage des tables `migrate_map_*` et `migrate_message_*`
4. Cache clear
5. Reinstallation du module (charge les 6 configs + group)
6. Execution des update hooks (`uic_config` pour `field_gallery`, `field_attachments`)
7. Verification (module, configs, champs, types de contenu)
8. (Optionnel) Import des contenus via URL

> **Note** : le script tolere le bug PHP Fatal de `Consolidation\Log\Logger` en capturant stdout au lieu de se fier aux exit codes.

## Troubleshooting

### Problemes courants

**CrowdSec bloque les requetes HTTP**
- Symptome : toutes les migrations URL echouent, `cURL error 6` ou timeout
- Solution : utiliser l'import par fichier local (voir section ci-dessus)

**Migration shows 0 items**
- **URL Import** : verifier la connectivite reseau et l'accessibilite de l'URL SPIP
- **File Import** : verifier le chemin du fichier et les permissions
- Verifier le XPath selector
- Logs : `drush watchdog:show --filter=spip_to_drupal`

**`field_tags:explode: NULL is not a string`**
- Cause : le champ `tags1` ou `group1` est vide dans le XML source
- Solution : le pipeline utilise `_tags_raw` avec `default_value: ''` (deja corrige)

**`PreExistingConfigException` a l'installation**
- Cause : des configs `migrate_plus.migration.spip_*` existent deja
- Solution : `./clean_install.sh` (supprime toutes les configs avant reinstallation)

**`PHP Fatal error: Consolidation\Log\Logger`**
- Cause : incompatibilite Drush/consolidation-log
- Impact : les commandes drush affichent un PHP Fatal mais fonctionnent quand meme
- Le `clean_install.sh` gere ce cas en ignorant les exit codes

**Images `cURL error 6: Could not resolve host`**
- Cause : URL relative dans le HTML SPIP (ex: `plugins/uictemplates/img/...`)
- Correction : le fix `SpipHtmlMediaEmbed.php` (`$config + $defaults` au lieu de `$defaults + $config`) resout les URLs relatives en absolues

### Debug

```bash
# Logs migration
drush watchdog:show --filter=spip_to_drupal --count=20

# Messages d'erreur d'une migration
drush sql:query "SELECT message FROM migrate_message_spip_project_pages LIMIT 10"

# Verifier la config active
drush config:get migrate_plus.migration.spip_project_pages process.field_tags --format=yaml

# Compter les nodes migres
drush sql:query "SELECT type, COUNT(*) FROM node_field_data GROUP BY type"

# Statut des migrations
drush migrate:status --group=spip_import
```

## Requirements

- Drupal 10
- Migrate API (`migrate`)
- Migrate Plus (`migrate_plus`)
- Migrate Tools (`migrate_tools`)
- Module `uic_config` (types de contenu et champs cibles)

## Changelog

### 2025-02-06 - Import par fichier local + corrections majeures

**Nouvelles fonctionnalites :**
- Ajout de 3 migrations locales (`*_local`) pour import par fichier XML sans requete HTTP
- Formulaire admin : champ upload XML (`managed_file`) + champ texte pour chemin serveur
- Source plugin : resolution multi-strategies des chemins (stream wrapper, absolu, relatif Drupal root, repertoire module)
- `count()` retourne 0 silencieusement quand le fichier local n'existe pas encore
- `getMigrationStatusInfo()` affiche les 6 migrations (3 URL + 3 local)
- `handleImportBatch()` supporte l'import local en une seule operation batch
- `clean_install.sh` mis a jour pour gerer les 6 configs + tables locales

**Corrections :**
- `SpipHtmlMediaEmbed.php` : correction `$config + $defaults` (les URLs relatives sont resolues correctement)
- `field_tags` : pipeline NULL-safe avec `_tags_raw` intermediaire + `strict: false` sur `explode`
- `spip_project_pages` : correction `default_bundle: project_page` (etait `article`)
- `clean_install.sh` : compatible Debian sans DDEV, tolerant au bug `Consolidation\Log\Logger`
- `determineMigrationId()` : retourne la bonne migration locale pour tous les bundles

### 2025-08-31 - Project Pages + Rubriques

- Ajout migration `spip_project_pages` pour les Project Pages
- Ajout migration `spip_rubriques` pour les Activity Pages
- Ajout `field_gallery` et `field_attachments` sur `project_page` (via `uic_config_update_9004`)
- Plugins `SpipPortfolioToMedia`, `SpipDocToEmbed` pour l'import des documents et images portfolio

### 2025-08-18 - Version initiale

- Migration `spip_enews_articles` avec auto-pagination
- Plugins de conversion SPIP : `SpipRaccourcisToHtml`, `SpipAutoLink`, `SpipHtmlTransform`, `SpipHtmlMediaEmbed`
- Plugin source `spip_xml_file` avec gestion des namespaces XML
- Interface d'administration `/admin/content/spip-migration`
- Script `clean_install.sh` pour reinstallation

## License

GPL-2.0-or-later
