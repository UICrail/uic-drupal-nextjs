#!/bin/bash
#
# ============================================================
#  SPIP to Drupal - Nettoyage complet et reinstallation
# ============================================================
#
#  Ce script :
#   1. Desinstalle le module spip_to_drupal
#   2. Supprime TOUTES les configs de migration SPIP (actives et orphelines)
#   3. Nettoie les tables de migration map en base
#   4. Reinstalle le module (charge les configs corrigees depuis config/install/)
#   5. Verifie que tout est correct
#   6. (Optionnel) Lance les migrations
#
#  Usage :
#    cd /chemin/vers/drupal/web/modules/custom/spip_to_drupal
#    chmod +x clean_install.sh
#    ./clean_install.sh            # Nettoyage + reinstallation
#    ./clean_install.sh --import   # Idem + lance toutes les migrations
#
#  Compatible : Debian, Ubuntu, serveur sans DDEV
#

set -euo pipefail

# ---- Options ----
RUN_IMPORT=false
if [ "${1:-}" = "--import" ]; then
    RUN_IMPORT=true
fi

# ---- Couleurs ----
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}[OK]${NC}    $1"; }
warn() { echo -e "  ${YELLOW}[WARN]${NC}  $1"; }
err()  { echo -e "  ${RED}[ERR]${NC}   $1"; }
info() { echo -e "  ${BLUE}[INFO]${NC}  $1"; }
step() { echo -e "\n${BOLD}=== $1 ===${NC}"; }

# ---- Trouver drush ----
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# web/modules/custom/spip_to_drupal -> remonter au-dessus de web/
DRUPAL_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"

if command -v drush &>/dev/null; then
    DRUSH="drush"
elif [ -x "$DRUPAL_ROOT/vendor/bin/drush" ]; then
    DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
elif [ -x "$DRUPAL_ROOT/../vendor/bin/drush" ]; then
    DRUSH="$DRUPAL_ROOT/../vendor/bin/drush"
else
    err "drush introuvable."
    err "Tentatives : drush, $DRUPAL_ROOT/vendor/bin/drush, $DRUPAL_ROOT/../vendor/bin/drush"
    exit 1
fi

info "drush : $DRUSH"
info "Drupal root : $DRUPAL_ROOT"
echo ""

# ============================================================
step "Etape 1/7 : Desinstallation du module"
# ============================================================

if $DRUSH pm:list --status=enabled --type=module 2>/dev/null | grep -q spip_to_drupal; then
    info "Desinstallation de spip_to_drupal..."
    $DRUSH pm:uninstall spip_to_drupal -y 2>/dev/null || true
    ok "Module desinstalle"
else
    ok "Module deja desinstalle"
fi

# ============================================================
step "Etape 2/7 : Suppression des configs de migration SPIP"
# ============================================================

# Liste de TOUTES les configs connues (actives, backup, anciennes)
CONFIGS=(
    # Migrations actuelles (config/install)
    "migrate_plus.migration.spip_enews_articles"
    "migrate_plus.migration.spip_project_pages"
    "migrate_plus.migration.spip_rubriques"
    "migrate_plus.migration_group.spip_import"
    # Anciennes migrations (backups / versions precedentes)
    "migrate_plus.migration.spip_enews_articles_local"
    "migrate_plus.migration.spip_enews_articles_bkp"
    "migrate_plus.migration.spip_enews_articles_auto_paginate"
    "migrate_plus.migration.spip_enews_articles_update"
    "migrate_plus.migration.spip_project_pages_local"
)

DELETED=0
for config in "${CONFIGS[@]}"; do
    if $DRUSH config:get "$config" &>/dev/null; then
        $DRUSH config:delete "$config" 2>/dev/null || true
        ok "Supprime : $config"
        ((DELETED++))
    fi
done

# Chercher d'autres configs SPIP orphelines
ORPHANS=$($DRUSH config:list 2>/dev/null | grep -i spip || true)
if [ -n "$ORPHANS" ]; then
    warn "Configs SPIP supplementaires trouvees :"
    while IFS= read -r line; do
        if [ -n "$line" ]; then
            $DRUSH config:delete "$line" 2>/dev/null || true
            ok "Supprime : $line"
            ((DELETED++))
        fi
    done <<< "$ORPHANS"
fi

info "$DELETED config(s) supprimee(s) au total"

# ============================================================
step "Etape 3/7 : Nettoyage des tables de migration en base"
# ============================================================

# Les tables migrate_map_* et migrate_message_* persistent apres desinstallation
TABLES=(
    "migrate_map_spip_enews_articles"
    "migrate_message_spip_enews_articles"
    "migrate_map_spip_enews_articles_local"
    "migrate_message_spip_enews_articles_local"
    "migrate_map_spip_enews_articles_bkp"
    "migrate_message_spip_enews_articles_bkp"
    "migrate_map_spip_enews_articles_auto_paginate"
    "migrate_message_spip_enews_articles_auto_paginate"
    "migrate_map_spip_enews_articles_update"
    "migrate_message_spip_enews_articles_update"
    "migrate_map_spip_project_pages"
    "migrate_message_spip_project_pages"
    "migrate_map_spip_project_pages_local"
    "migrate_message_spip_project_pages_local"
    "migrate_map_spip_rubriques"
    "migrate_message_spip_rubriques"
)

TABLES_DROPPED=0
for table in "${TABLES[@]}"; do
    if $DRUSH sql:query "SELECT 1 FROM information_schema.tables WHERE table_name = '$table' LIMIT 1" 2>/dev/null | grep -q 1; then
        $DRUSH sql:query "DROP TABLE IF EXISTS $table" 2>/dev/null || true
        ok "Table supprimee : $table"
        ((TABLES_DROPPED++))
    fi
done

info "$TABLES_DROPPED table(s) de migration supprimee(s)"

# ============================================================
step "Etape 4/7 : Nettoyage du cache"
# ============================================================

$DRUSH cr 2>/dev/null
ok "Cache Drupal vide"

# ============================================================
step "Etape 5/7 : Reinstallation du module"
# ============================================================

info "Activation de spip_to_drupal et dependances..."
$DRUSH pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y 2>&1
ok "Module installe"

$DRUSH cr 2>/dev/null
ok "Cache vide apres installation"

# ============================================================
step "Etape 6/7 : Verification"
# ============================================================

# Module actif ?
if $DRUSH pm:list --status=enabled --type=module 2>/dev/null | grep -q spip_to_drupal; then
    ok "Module spip_to_drupal actif"
else
    err "Module spip_to_drupal NON actif !"
    exit 1
fi

# Configs chargees ?
MIGRATIONS_OK=true
for mid in spip_enews_articles spip_project_pages spip_rubriques; do
    if $DRUSH config:get "migrate_plus.migration.$mid" id &>/dev/null; then
        ok "Config chargee : $mid"
    else
        err "Config MANQUANTE : $mid"
        MIGRATIONS_OK=false
    fi
done

if $DRUSH config:get migrate_plus.migration_group.spip_import id &>/dev/null; then
    ok "Config chargee : spip_import (group)"
else
    err "Config MANQUANTE : spip_import (group)"
    MIGRATIONS_OK=false
fi

# Valeurs corrigees pour project_pages ?
PP_TYPE=$($DRUSH config:get migrate_plus.migration.spip_project_pages process.type.default_value 2>/dev/null || echo "?")
PP_BUNDLE=$($DRUSH config:get migrate_plus.migration.spip_project_pages destination.default_bundle 2>/dev/null || echo "?")

if echo "$PP_TYPE" | grep -q "project_page"; then
    ok "spip_project_pages type = project_page"
else
    err "spip_project_pages type = $PP_TYPE (attendu: project_page)"
    MIGRATIONS_OK=false
fi

if echo "$PP_BUNDLE" | grep -q "project_page"; then
    ok "spip_project_pages bundle = project_page"
else
    err "spip_project_pages bundle = $PP_BUNDLE (attendu: project_page)"
    MIGRATIONS_OK=false
fi

# Types de contenu cibles ?
for bundle in article activity_page project_page; do
    EXISTS=$($DRUSH php:eval "
use Drupal\node\Entity\NodeType;
echo NodeType::load('$bundle') ? 'yes' : 'no';
" 2>/dev/null || echo "no")
    if [ "$EXISTS" = "yes" ]; then
        ok "Type de contenu : $bundle"
    else
        warn "Type de contenu MANQUANT : $bundle (verifiez uic_config)"
    fi
done

# Statut des migrations
echo ""
info "Statut des migrations :"
$DRUSH migrate:status --group=spip_import 2>/dev/null || warn "Impossible d'afficher le statut"

# ============================================================
step "Etape 7/7 : Import des contenus"
# ============================================================

if [ "$RUN_IMPORT" = true ]; then
    info "Lancement des 3 migrations..."

    echo ""
    info "--- spip_enews_articles (Articles) ---"
    $DRUSH migrate:import spip_enews_articles 2>&1 || warn "Migration articles terminee avec warnings"

    echo ""
    info "--- spip_rubriques (Activity Pages) ---"
    $DRUSH migrate:import spip_rubriques 2>&1 || warn "Migration rubriques terminee avec warnings"

    echo ""
    info "--- spip_project_pages (Project Pages) ---"
    $DRUSH migrate:import spip_project_pages 2>&1 || warn "Migration project pages terminee avec warnings"

    echo ""
    info "Resume des contenus migres :"
    for bundle in article activity_page project_page; do
        COUNT=$($DRUSH sql:query "SELECT COUNT(*) FROM node_field_data WHERE type = '$bundle'" 2>/dev/null || echo "?")
        info "  $bundle : $COUNT node(s)"
    done
else
    info "Import non demande. Pour importer :"
    info "  ./clean_install.sh --import"
    info ""
    info "Ou migration par migration :"
    info "  $DRUSH migrate:import spip_enews_articles"
    info "  $DRUSH migrate:import spip_rubriques"
    info "  $DRUSH migrate:import spip_project_pages"
fi

# ---- Fin ----
echo ""
ok "Nettoyage et reinstallation termines."
info "Interface admin : /admin/content/spip-migration"
info "Logs : $DRUSH watchdog:show --filter=spip_to_drupal --count=20"
echo ""
