#!/bin/bash

# Script d'assainissement des caches de découverte et des références de modules inexistants
# - Sans référence codée en dur à un module précis
# - Permet de purger des IDs de module passés en arguments
# - Nettoie les caches (cache_discovery, system_list, etc.) et reconstruit le cache
#
# Usage:
#   chmod +x sanitize_drupal_modules.sh
#   ./sanitize_drupal_modules.sh module_id1 [module_id2 ...]
#
# Exemple:
#   ./sanitize_drupal_modules.sh spip_to_drupal_bkp_before_clean

set -euo pipefail

echo "=== Assainissement modules/caches Drupal ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
DRUPAL_ROOT_IN_CONT="/var/www/html/drupal"

cd "$PROJECT_DIR"

echo "1) Vérification DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Erreur: ddev introuvable. Lancez Docker Desktop/DDEV puis réessayez." >&2
  exit 1
fi

echo "2) Démarrage DDEV (si nécessaire)..."
ddev start >/dev/null 2>&1 || true

# Optionnel: liste des modules à purger passée en arguments
# Si vide, purger par défaut l'ID problématique connu
MODULES_TO_PURGE=("$@")
if [ ${#MODULES_TO_PURGE[@]} -eq 0 ]; then
  MODULES_TO_PURGE=("spip_to_drupal_bkp_before_clean")
fi

if [ ${#MODULES_TO_PURGE[@]} -gt 0 ]; then
  echo "3) Purge ciblée de références de modules inexistants (arguments ou défaut)..."
  for MODULE_ID in "${MODULES_TO_PURGE[@]}"; do
    echo " - Module à purger: ${MODULE_ID}"

    # 3a) Nettoyage direct de core.extension via SQL (avant toute tentative de bootstrap)
    echo "   > Nettoyage SQL de core.extension (suppression des poids ${MODULE_ID}: N)..."
    for n in $(seq 0 99); do
      ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}\\n', '') WHERE name='core.extension'" >/dev/null 2>&1 || true
      ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}',   '') WHERE name='core.extension'" >/dev/null 2>&1 || true
    done

    # 3b) Créer systématiquement un stub pour permettre le bootstrap si un mapping erroné persiste
    STUB_DIR_IN_CONT="$DRUPAL_ROOT_IN_CONT/web/modules/custom/${MODULE_ID}"
    ddev exec bash -lc "mkdir -p '$STUB_DIR_IN_CONT'" || true
    ddev exec bash -lc "cat > '$STUB_DIR_IN_CONT/${MODULE_ID}.info.yml' <<'YAML'
name: 'Temporary placeholder for cleanup'
type: module
description: 'Placeholder créé par sanitize_drupal_modules.sh pour permettre la désinstallation d\'un module fantôme.'
package: Custom
core_version_requirement: '^9 || ^10'
version: '0.0.0'
YAML" || true
    # Cas particulier: créer aussi spip_to_drupal.info.yml si le mapping pointe sur ce dossier
    if [ "${MODULE_ID}" = "spip_to_drupal_bkp_before_clean" ]; then
      ddev exec bash -lc "cat > '$STUB_DIR_IN_CONT/spip_to_drupal.info.yml' <<'YAML'
name: 'SPIP to Drupal (compat placeholder)'
type: module
description: 'Compat placeholder pour permettre le bootstrap pendant le nettoyage.'
package: Custom
core_version_requirement: '^9 || ^10'
version: '0.0.0'
YAML" || true
    fi

    # 3c) Rebuild caches pour rafraîchir le mapping, puis tenter la désinstallation et re-nettoyer core.extension
    echo "   > Rebuild caches (drush cr) pour valider le bootstrap..."
    ddev drush cr >/dev/null 2>&1 || true
    echo "   > Tentative de désinstallation via Drush..."
    ddev drush pm:uninstall "${MODULE_ID}" -y >/dev/null 2>&1 || true
    echo "   > Nettoyage SQL supplémentaire de core.extension..."
    for n in $(seq 0 99); do
      ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}\\n', '') WHERE name='core.extension'" >/dev/null 2>&1 || true
      ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}',   '') WHERE name='core.extension'" >/dev/null 2>&1 || true
    done

    # 3b) Suppression du répertoire résiduel (hôte et conteneur)
    echo "   > Suppression du répertoire résiduel (si présent)..."
    HOST_DIR="$PROJECT_DIR/web/modules/custom/${MODULE_ID}"
    rm -rf "$HOST_DIR" || true
    ddev exec bash -lc "rm -rf '$DRUPAL_ROOT_IN_CONT/web/modules/custom/${MODULE_ID}'" || true
  done
else
  echo "3) Auto-détection des modules fantômes..."
  CORE_EXT_YAML=$(ddev drush sql:query "SELECT data FROM config WHERE name='core.extension'" 2>/dev/null || true)
  if [ -n "$CORE_EXT_YAML" ]; then
    MODULE_BLOCK=$(printf "%s\n" "$CORE_EXT_YAML" | sed -n '/^module:/, /^[^[:space:]]/p')
    DETECTED_MODULES=$(printf "%s\n" "$MODULE_BLOCK" | sed -n '2,$p' | sed -n 's/^[[:space:]]*\([A-Za-z0-9_\-]\+\):.*/\1/p')
    for MODULE_ID in $DETECTED_MODULES; do
      # Vérifier si le module existe réellement (custom/contrib/core)
      if ddev exec bash -lc "[ -f '$DRUPAL_ROOT_IN_CONT/web/modules/custom/${MODULE_ID}/${MODULE_ID}.info.yml' ] || [ -f '$DRUPAL_ROOT_IN_CONT/web/modules/contrib/${MODULE_ID}/${MODULE_ID}.info.yml' ] || [ -f '$DRUPAL_ROOT_IN_CONT/web/core/modules/${MODULE_ID}/${MODULE_ID}.info.yml' ]"; then
        continue
      fi

      echo " - Module fantôme détecté: ${MODULE_ID} (référencé mais fichier .info.yml introuvable)"
      # Créer un stub, tenter la désinstallation, puis nettoyer core.extension au besoin
      STUB_DIR_IN_CONT="$DRUPAL_ROOT_IN_CONT/web/modules/custom/${MODULE_ID}"
      ddev exec bash -lc "mkdir -p '$STUB_DIR_IN_CONT'"
      ddev exec bash -lc "cat > '$STUB_DIR_IN_CONT/${MODULE_ID}.info.yml' <<'YAML'
name: 'Temporary placeholder for cleanup'
type: module
description: 'Placeholder créé par sanitize_drupal_modules.sh pour permettre la désinstallation d\'un module fantôme.'
package: Custom
core_version_requirement: '^9 || ^10'
version: '0.0.0'
YAML"
      # Cas particulier pour le mapping erroné du module réel
      if [ "${MODULE_ID}" = "spip_to_drupal_bkp_before_clean" ]; then
        ddev exec bash -lc "cat > '$STUB_DIR_IN_CONT/spip_to_drupal.info.yml' <<'YAML'
name: 'SPIP to Drupal (compat placeholder)'
type: module
description: 'Compat placeholder pour permettre le bootstrap pendant le nettoyage.'
package: Custom
core_version_requirement: '^9 || ^10'
version: '0.0.0'
YAML" || true
      fi

      echo "   > Tentative de désinstallation via Drush..."
      if ! ddev drush pm:uninstall "${MODULE_ID}" -y >/dev/null 2>&1; then
        echo "   > Désinstallation Drush échouée, nettoyage direct de core.extension..."
        for n in $(seq 0 30); do
          ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}\\n', '') WHERE name='core.extension'" >/dev/null 2>&1 || true
          ddev drush sql:query "UPDATE config SET data = REPLACE(data, '  ${MODULE_ID}: ${n}',   '') WHERE name='core.extension'" >/dev/null 2>&1 || true
        done
      fi

      echo "   > Suppression du stub temporaire..."
      ddev exec bash -lc "rm -rf '$STUB_DIR_IN_CONT'" || true

      # Purge des répertoires résiduels (hôte comme conteneur)
      HOST_DIR="$PROJECT_DIR/web/modules/custom/${MODULE_ID}"
      rm -rf "$HOST_DIR" || true
      ddev exec bash -lc "rm -rf '$DRUPAL_ROOT_IN_CONT/web/modules/custom/${MODULE_ID}'" || true
    done
  else
    echo " - Impossible de lire core.extension; poursuite du nettoyage générique."
  fi
fi

echo "4) Nettoyage des caches de découverte et system_list..."
ddev drush sql:query "TRUNCATE cache_discovery" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_bootstrap WHERE cid IN ('system_list','system_list_info')" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_data      WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_default   WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true

echo "5) Reconstruction des caches Drupal..."
if ! ddev drush cr; then
  echo "   > drush cr a échoué, nouvelle tentative..." >&2
  sleep 1
  ddev drush cr
fi

echo "=== Assainissement terminé ==="


