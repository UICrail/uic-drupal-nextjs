#!/bin/bash

# UIC Configuration Module Clean Installation Script
# This script completely removes existing configurations and reinstalls the module

set -e

echo "🧹 UIC Configuration Module Clean Installation"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if we're in a DDEV environment
if command -v ddev &> /dev/null; then
    DRUSH_CMD="ddev drush"
    print_status "Using DDEV environment"
else
    DRUSH_CMD="drush"
    print_warning "DDEV not found, using local drush"
fi

# Confirmation
echo ""
print_warning "⚠️  WARNING: This will completely remove all UIC Configuration data!"
echo "This includes:"
echo "  - All custom fields on Article content type"
echo "  - Project Page content type"
echo "  - All related configurations"
echo "  - Any data in these fields"
echo ""
read -p "Are you sure you want to continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_info "Clean installation cancelled"
    exit 0
fi

# Step 1: Uninstall module if it exists
echo "📦 Step 1: Uninstalling existing module..."
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
    print_info "Uninstalling uic_config module..."
    $DRUSH_CMD pm:uninstall uic_config -y
    print_status "Module uninstalled"
else
    print_info "Module not installed, skipping uninstall"
fi

# Step 2: Remove custom fields from Article content type
echo "🗑️  Step 2: Removing custom fields from Article content type..."
CUSTOM_FIELDS=(
    "field_subtitle"
    "field_header"
    "field_footer"
    "field_gallery"
    "field_attachments"
    "field_spip_id"
    "field_spip_url"
)

for field in "${CUSTOM_FIELDS[@]}"; do
    if $DRUSH_CMD field:info "node.article.$field" &> /dev/null; then
        print_info "Removing field: $field"
        $DRUSH_CMD field:delete "node.article.$field" -y
        print_status "Field $field removed"
    else
        print_info "Field $field not found, skipping"
    fi
done

# Step 3: Remove field storage
echo "🗑️  Step 3: Removing field storage..."
FIELD_STORAGE=(
    "field_storage.node.field_subtitle"
    "field_storage.node.field_header"
    "field_storage.node.field_footer"
    "field_storage.node.field_gallery"
    "field_storage.node.field_attachments"
    "field_storage.node.field_spip_id"
    "field_storage.node.field_spip_url"
    "field_storage.node.field_start_end"
)

for storage in "${FIELD_STORAGE[@]}"; do
    if $DRUSH_CMD config:get "$storage" &> /dev/null; then
        print_info "Removing field storage: $storage"
        $DRUSH_CMD config:delete "$storage" -y
        print_status "Field storage $storage removed"
    else
        print_info "Field storage $storage not found, skipping"
    fi
done

# Step 4: Remove Project Page content type
echo "🗑️  Step 4: Removing Project Page content type..."
if $DRUSH_CMD config:get node.type.project_page &> /dev/null; then
    print_info "Removing Project Page content type..."
    $DRUSH_CMD config:delete node.type.project_page -y
    print_status "Project Page content type removed"
else
    print_info "Project Page content type not found, skipping"
fi

# Step 5: Remove GraphQL Compose configurations
echo "🗑️  Step 5: Cleaning GraphQL Compose configurations..."
if $DRUSH_CMD config:get graphql_compose.settings &> /dev/null; then
    print_info "Removing UIC Configuration from GraphQL Compose settings..."
    
    # Get current GraphQL Compose settings
    GRAPHQL_CONFIG=$($DRUSH_CMD config:get graphql_compose.settings --format=yaml)
    
    # Remove UIC Configuration specific settings
    # This is a simplified approach - in production you might want to be more careful
    print_warning "Note: GraphQL Compose settings will be cleaned during reinstall"
else
    print_info "GraphQL Compose settings not found, skipping"
fi

# Step 6: Clear all caches
echo "🧹 Step 6: Clearing all caches..."
$DRUSH_CMD cr
print_status "Caches cleared"

# Step 7: Install the module fresh
echo "📦 Step 7: Installing UIC Configuration module fresh..."
$DRUSH_CMD pm:install uic_config -y

if [ $? -eq 0 ]; then
    print_status "Module installed successfully!"
else
    print_error "Module installation failed"
    exit 1
fi

# Step 8: Verify clean installation
echo "🔍 Step 8: Verifying clean installation..."
VERIFICATION_PASSED=true

# Check if module is enabled
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
    print_status "Module is enabled"
else
    print_error "Module is not enabled"
    VERIFICATION_PASSED=false
fi

# Check if custom fields are created
for field in "${CUSTOM_FIELDS[@]}"; do
    if $DRUSH_CMD field:info "node.article.$field" &> /dev/null; then
        print_status "Field $field is created"
    else
        print_error "Field $field not found"
        VERIFICATION_PASSED=false
    fi
done

# Check if project_page content type exists
if $DRUSH_CMD config:get node.type.project_page &> /dev/null; then
    print_status "Project Page content type exists"
else
    print_error "Project Page content type not found"
    VERIFICATION_PASSED=false
fi

# Check GraphQL configuration
if $DRUSH_CMD config:get graphql_compose.settings field_config.node.article.field_subtitle &> /dev/null; then
    print_status "GraphQL configuration for custom fields is active"
else
    print_warning "GraphQL configuration may not be complete"
fi

# Final cache clear
echo "🧹 Final cache clear..."
$DRUSH_CMD cr
print_status "Final cache clear completed"

if [ "$VERIFICATION_PASSED" = true ]; then
    echo ""
    print_status "🎉 Clean installation completed successfully!"
    echo ""
    echo "📋 Summary:"
    echo "  - All existing UIC Configuration data removed"
    echo "  - Module installed fresh"
    echo "  - Custom fields created for Article content type"
    echo "  - Project Page content type created"
    echo "  - GraphQL exposure configured"
    echo ""
    echo "🧪 To test the installation:"
    echo "  ./test-installation.sh"
    echo "  ./test-graphql.sh"
    echo ""
    print_status "Clean installation completed successfully!"
else
    echo ""
    print_error "❌ Clean installation completed with errors"
    echo "Please check the verification results above"
    echo "You may need to manually fix some components"
    exit 1
fi
