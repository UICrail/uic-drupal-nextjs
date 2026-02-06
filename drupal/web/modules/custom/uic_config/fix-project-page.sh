#!/bin/bash

# Script to diagnose and fix Project Page content type creation
# Run this script from the Drupal root directory

echo "=== UIC Config: Project Page Diagnostic ==="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if drush is available
if ! command -v drush &> /dev/null; then
    echo -e "${RED}Error: drush not found${NC}"
    exit 1
fi

echo "1. Checking if uic_config module is installed..."
MODULE_STATUS=$(drush pm:list --status=enabled --type=module 2>/dev/null | grep uic_config)
if [ -z "$MODULE_STATUS" ]; then
    echo -e "${RED}   Module uic_config is NOT installed${NC}"
    echo "   Run: drush pm:install uic_config -y"
    exit 1
else
    echo -e "${GREEN}   Module uic_config is installed${NC}"
fi

echo ""
echo "2. Checking existing content types..."
drush php:eval "
use Drupal\node\Entity\NodeType;
\$types = ['article', 'activity_page', 'project_page'];
foreach (\$types as \$type) {
    \$exists = NodeType::load(\$type) ? 'EXISTS' : 'MISSING';
    echo \"   - \$type: \$exists\n\";
}
"

echo ""
echo "3. Checking update hooks status..."
drush php:eval "
\$schema = \Drupal::keyValue('system.schema')->get('uic_config');
echo \"   Current schema version: \$schema\n\";
"

echo ""
echo "4. Checking recent logs for uic_config..."
drush watchdog:show --filter='uic_config' --count=10 2>/dev/null || echo "   No recent logs found"

echo ""
echo "=== Attempting to fix ==="
echo ""

echo "5. Running pending update hooks..."
drush updatedb -y

echo ""
echo "6. Forcing Project Page creation via PHP..."
drush php:eval "
use Drupal\node\Entity\NodeType;

\$bundle = 'project_page';
if (NodeType::load(\$bundle)) {
    echo \"Project Page already exists.\n\";
} else {
    echo \"Creating Project Page content type...\n\";
    try {
        \$type = NodeType::create([
            'type' => \$bundle,
            'name' => 'Project Page',
            'description' => 'Use project pages for showcasing projects, case studies, or portfolio items.',
            'new_revision' => TRUE,
            'preview_mode' => 1,
            'display_submitted' => FALSE,
        ]);
        \$type->save();
        echo \"SUCCESS: Project Page content type created.\n\";
    } catch (\Exception \$e) {
        echo \"ERROR: \" . \$e->getMessage() . \"\n\";
    }
}
"

echo ""
echo "7. Creating Project Page fields..."
drush php:eval "
// Call the field creation function
if (function_exists('_uic_config_create_project_page_fields')) {
    _uic_config_create_project_page_fields();
    echo \"Fields creation function called.\n\";
} else {
    echo \"Function not found - module may need reload.\n\";
}
"

echo ""
echo "8. Clearing caches..."
drush cr

echo ""
echo "9. Final verification..."
drush php:eval "
use Drupal\node\Entity\NodeType;
\$type = NodeType::load('project_page');
if (\$type) {
    echo \"SUCCESS: Project Page content type exists.\n\";
    echo \"   Name: \" . \$type->label() . \"\n\";
    echo \"   Machine name: \" . \$type->id() . \"\n\";
} else {
    echo \"FAILED: Project Page still does not exist.\n\";
}
"

echo ""
echo "=== Diagnostic complete ==="
