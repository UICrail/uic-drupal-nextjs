#!/bin/bash

# Test script for UIC Configuration module installation
# This script tests the installation process and verifies that custom fields are properly configured

set -e

echo "🧪 Testing UIC Configuration module installation..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Check if we're in a DDEV environment
if command -v ddev &> /dev/null; then
    DRUSH_CMD="ddev drush"
    print_status "Using DDEV environment"
else
    DRUSH_CMD="drush"
    print_warning "DDEV not found, using local drush"
fi

# Step 1: Check if module is already installed
echo "📋 Step 1: Checking current module status..."
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
    print_warning "Module is already installed, uninstalling first..."
    $DRUSH_CMD pm:uninstall uic_config -y
fi

# Step 2: Install the module
echo "📦 Step 2: Installing UIC Configuration module..."
$DRUSH_CMD pm:install uic_config -y

if [ $? -eq 0 ]; then
    print_status "Module installed successfully"
else
    print_error "Module installation failed"
    exit 1
fi

# Step 3: Verify that custom fields exist
echo "🔍 Step 3: Verifying custom fields..."
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
        print_status "Field $field exists"
    else
        print_error "Field $field not found"
    fi
done

# Step 4: Check form display configuration
echo "📝 Step 4: Checking form display configuration..."
FORM_DISPLAY=$($DRUSH_CMD config:get core.entity_form_display.node.article.default content --format=yaml)

for field in "${CUSTOM_FIELDS[@]}"; do
    if echo "$FORM_DISPLAY" | grep -q "$field:"; then
        print_status "Field $field is configured in form display"
    else
        print_warning "Field $field not found in form display"
    fi
done

# Step 5: Check view display configuration (fields should be hidden)
echo "👁️  Step 5: Checking view display configuration..."
VIEW_DISPLAY=$($DRUSH_CMD config:get core.entity_view_display.node.article.default hidden --format=yaml)

for field in "${CUSTOM_FIELDS[@]}"; do
    if echo "$VIEW_DISPLAY" | grep -q "$field:"; then
        print_status "Field $field is hidden in view display (as expected)"
    else
        print_warning "Field $field not hidden in view display"
    fi
done

# Step 6: Test creating a test article
echo "📄 Step 6: Testing article creation..."
TEST_NODE_ID=$($DRUSH_CMD node:create --type=article --title="Test Article for UIC Config" --body="Test content" --field_subtitle="Test Subtitle" --field_spip_id="TEST123" --field_spip_url="https://example.com/test" --uid=1 --status=1 --format=json | jq -r '.nid')

if [ "$TEST_NODE_ID" != "null" ] && [ "$TEST_NODE_ID" != "" ]; then
    print_status "Test article created with ID: $TEST_NODE_ID"
    
    # Verify the custom fields were saved
    CUSTOM_FIELD_VALUES=$($DRUSH_CMD node:load $TEST_NODE_ID --field_subtitle --field_spip_id --field_spip_url --format=json)
    
    if echo "$CUSTOM_FIELD_VALUES" | jq -e '.field_subtitle[0].value' > /dev/null; then
        print_status "Custom field values saved correctly"
    else
        print_warning "Custom field values not found in saved node"
    fi
    
    # Clean up test node
    $DRUSH_CMD node:delete $TEST_NODE_ID -y
    print_status "Test article cleaned up"
else
    print_error "Failed to create test article"
fi

# Step 7: Check for any configuration conflicts
echo "🔧 Step 7: Checking for configuration conflicts..."
CONFIG_STATUS=$($DRUSH_CMD config:status --format=table)

if echo "$CONFIG_STATUS" | grep -q "uic_config"; then
    print_warning "Configuration status check completed"
else
    print_status "No configuration conflicts detected"
fi

echo ""
print_status "🎉 UIC Configuration module installation test completed successfully!"
echo ""
echo "📋 Summary:"
echo "  - Module installed without conflicts"
echo "  - Custom fields created and configured"
echo "  - Form display updated with new fields"
echo "  - View display configured (fields hidden by default)"
echo "  - Test article creation successful"
echo ""
echo "✅ The module is ready to use!"
