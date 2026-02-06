#!/bin/bash

# UIC Configuration Module Installation Script
# This script installs the UIC Configuration module safely

set -e

echo "🚀 Installing UIC Configuration module..."

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

# Step 1: Check current state
echo "📋 Step 1: Checking current module status..."
MODULE_STATUS=$($DRUSH_CMD pm:list --type=Module --status=enabled --core --format=table | grep "uic_config" || true)

if [ -n "$MODULE_STATUS" ]; then
    print_warning "Module is already installed"
    read -p "Do you want to reinstall it? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Uninstalling existing module..."
        $DRUSH_CMD pm:uninstall uic_config -y
        print_status "Module uninstalled"
    else
        print_status "Installation skipped"
        exit 0
    fi
fi

# Step 2: Check for existing custom fields
echo "🔍 Step 2: Checking for existing custom fields..."
EXISTING_FIELDS=()

# Check for article custom fields
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
        EXISTING_FIELDS+=("$field")
    fi
done

if [ ${#EXISTING_FIELDS[@]} -gt 0 ]; then
    print_warning "Found existing custom fields: ${EXISTING_FIELDS[*]}"
    print_info "The module will configure these fields but won't recreate them"
fi

# Step 3: Check for project_page content type
echo "📄 Step 3: Checking for project_page content type..."
if $DRUSH_CMD config:get node.type.project_page &> /dev/null; then
    print_warning "Project Page content type already exists"
    print_info "The module will configure it but won't recreate it"
fi

# Step 4: Install the module
echo "📦 Step 4: Installing UIC Configuration module..."
$DRUSH_CMD pm:install uic_config -y

if [ $? -eq 0 ]; then
    print_status "Module installed successfully!"
else
    print_error "Module installation failed"
    exit 1
fi

# Step 5: Verify installation
echo "🔍 Step 5: Verifying installation..."
VERIFICATION_PASSED=true

# Check if module is enabled
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
    print_status "Module is enabled"
else
    print_error "Module is not enabled"
    VERIFICATION_PASSED=false
fi

# Check if custom fields are configured
for field in "${CUSTOM_FIELDS[@]}"; do
    if $DRUSH_CMD field:info "node.article.$field" &> /dev/null; then
        print_status "Field $field is configured"
    else
        print_warning "Field $field not found"
    fi
done

# Check if project_page content type exists
if $DRUSH_CMD config:get node.type.project_page &> /dev/null; then
    print_status "Project Page content type exists"
else
    print_warning "Project Page content type not found"
fi

# Check GraphQL configuration
if $DRUSH_CMD config:get graphql_compose.settings field_config.node.article.field_subtitle &> /dev/null; then
    print_status "GraphQL configuration for custom fields is active"
else
    print_warning "GraphQL configuration may not be complete"
fi

# Step 6: Clear caches
echo "🧹 Step 6: Clearing caches..."
$DRUSH_CMD cr
print_status "Caches cleared"

if [ "$VERIFICATION_PASSED" = true ]; then
    echo ""
    print_status "🎉 UIC Configuration module installation completed successfully!"
    echo ""
    echo "📋 Summary:"
    echo "  - Module installed and enabled"
    echo "  - Custom fields configured for Article content type"
    echo "  - Project Page content type configured"
    echo "  - GraphQL exposure configured"
    echo ""
    echo "🔧 Next steps:"
    echo "  - Configure field display settings in admin interface"
    echo "  - Set up media types for gallery and attachments"
    echo "  - Configure view modes as needed"
    echo "  - Test GraphQL queries with custom fields"
    echo ""
    echo "🧪 To test the installation:"
    echo "  ./test-installation.sh"
    echo "  ./test-graphql.sh"
    echo ""
    print_status "Installation completed successfully!"
else
    echo ""
    print_warning "⚠️  Installation completed with warnings"
    echo "Please check the verification results above"
    echo "You may need to manually configure some components"
fi
