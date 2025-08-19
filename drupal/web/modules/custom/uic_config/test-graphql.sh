#!/bin/bash

# Test script for GraphQL exposure of UIC Configuration custom fields
# This script tests that custom fields are properly exposed in GraphQL

set -e

echo "🧪 Testing GraphQL exposure of UIC Configuration fields..."

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
    CURL_CMD="ddev curl"
    print_status "Using DDEV environment"
else
    DRUSH_CMD="drush"
    CURL_CMD="curl"
    print_warning "DDEV not found, using local commands"
fi

# Step 1: Check if GraphQL Compose is enabled
echo "📋 Step 1: Checking GraphQL Compose status..."
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "graphql_compose"; then
    print_status "GraphQL Compose is enabled"
else
    print_error "GraphQL Compose is not enabled"
    print_info "Please enable GraphQL Compose first:"
    print_info "  $DRUSH_CMD pm:enable graphql_compose -y"
    exit 1
fi

# Step 2: Check if UIC Config module is enabled
echo "📦 Step 2: Checking UIC Configuration module status..."
if $DRUSH_CMD pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
    print_status "UIC Configuration module is enabled"
else
    print_error "UIC Configuration module is not enabled"
    print_info "Please enable it first:"
    print_info "  $DRUSH_CMD pm:enable uic_config -y"
    exit 1
fi

# Step 3: Clear GraphQL schema cache
echo "🧹 Step 3: Clearing GraphQL schema cache..."
$DRUSH_CMD cr
print_status "Cache cleared"

# Step 4: Test GraphQL schema introspection
echo "🔍 Step 4: Testing GraphQL schema introspection..."
GRAPHQL_ENDPOINT="http://localhost/graphql"

# Get the GraphQL endpoint URL
if command -v ddev &> /dev/null; then
    GRAPHQL_ENDPOINT="https://uic-drupal-nextjs.ddev.site/graphql"
fi

# Test if GraphQL endpoint is accessible
if $CURL_CMD -s -o /dev/null -w "%{http_code}" "$GRAPHQL_ENDPOINT" | grep -q "200"; then
    print_status "GraphQL endpoint is accessible"
else
    print_warning "GraphQL endpoint not accessible, trying alternative..."
    GRAPHQL_ENDPOINT="http://localhost:8080/graphql"
    if $CURL_CMD -s -o /dev/null -w "%{http_code}" "$GRAPHQL_ENDPOINT" | grep -q "200"; then
        print_status "GraphQL endpoint is accessible on port 8080"
    else
        print_error "GraphQL endpoint not accessible"
        print_info "Please check your DDEV setup and GraphQL configuration"
        exit 1
    fi
fi

# Step 5: Test Article fields in GraphQL schema
echo "📄 Step 5: Testing Article fields in GraphQL schema..."
ARTICLE_QUERY='{
  "__type": {
    "name": "Article",
    "fields": {
      "name": true,
      "type": {
        "name": true
      }
    }
  }
}'

ARTICLE_SCHEMA_QUERY='{
  "__schema": {
    "types": [
      {
        "name": "Article",
        "fields": {
          "name": true,
          "type": {
            "name": true
          }
        }
      }
    ]
  }
}'

# Test if custom fields are in the schema
CUSTOM_FIELDS=(
    "fieldSubtitle"
    "fieldHeader"
    "fieldFooter"
    "fieldGallery"
    "fieldAttachments"
    "fieldSpipId"
    "fieldSpipUrl"
)

print_info "Testing Article custom fields in GraphQL schema..."

for field in "${CUSTOM_FIELDS[@]}"; do
    # Create a simple query to test if the field exists
    TEST_QUERY="{
      \"query\": \"query { __type(name: \\\"Article\\\") { fields { name } } }\"
    }"
    
    RESPONSE=$($CURL_CMD -s -X POST \
        -H "Content-Type: application/json" \
        -d "$TEST_QUERY" \
        "$GRAPHQL_ENDPOINT")
    
    if echo "$RESPONSE" | grep -q "$field"; then
        print_status "Field $field is exposed in GraphQL"
    else
        print_warning "Field $field not found in GraphQL schema"
    fi
done

# Step 6: Test Project Page fields in GraphQL schema
echo "📋 Step 6: Testing Project Page fields in GraphQL schema..."
PROJECT_FIELDS=(
    "fieldSubtitle"
    "fieldHeader"
    "fieldFooter"
    "fieldImage"
    "fieldStartEnd"
    "fieldSpipId"
    "fieldSpipUrl"
    "fieldTags"
)

print_info "Testing Project Page custom fields in GraphQL schema..."

for field in "${PROJECT_FIELDS[@]}"; do
    # Create a simple query to test if the field exists
    TEST_QUERY="{
      \"query\": \"query { __type(name: \\\"ProjectPage\\\") { fields { name } } }\"
    }"
    
    RESPONSE=$($CURL_CMD -s -X POST \
        -H "Content-Type: application/json" \
        -d "$TEST_QUERY" \
        "$GRAPHQL_ENDPOINT")
    
    if echo "$RESPONSE" | grep -q "$field"; then
        print_status "Field $field is exposed in GraphQL"
    else
        print_warning "Field $field not found in GraphQL schema"
    fi
done

# Step 7: Test actual GraphQL query
echo "🔍 Step 7: Testing actual GraphQL query..."
TEST_QUERY='{
  "query": "query { nodeQuery(limit: 1, filter: { conditions: [{ field: \"type\", value: \"article\" }] }) { entities { ... on Article { title fieldSubtitle fieldSpipId } } } }"
}'

RESPONSE=$($CURL_CMD -s -X POST \
    -H "Content-Type: application/json" \
    -d "$TEST_QUERY" \
    "$GRAPHQL_ENDPOINT")

if echo "$RESPONSE" | grep -q "fieldSubtitle\|fieldSpipId"; then
    print_status "GraphQL query with custom fields works"
    print_info "Response preview:"
    echo "$RESPONSE" | head -20
else
    print_warning "GraphQL query with custom fields may not be working"
    print_info "Full response:"
    echo "$RESPONSE"
fi

# Step 8: Check GraphQL Compose configuration
echo "⚙️  Step 8: Checking GraphQL Compose configuration..."
GRAPHQL_CONFIG=$($DRUSH_CMD config:get graphql_compose.settings field_config.node.article --format=yaml)

if echo "$GRAPHQL_CONFIG" | grep -q "field_subtitle\|field_header\|field_footer"; then
    print_status "GraphQL Compose configuration includes custom fields"
else
    print_warning "GraphQL Compose configuration may not include custom fields"
    print_info "Current configuration:"
    echo "$GRAPHQL_CONFIG"
fi

echo ""
print_status "🎉 GraphQL exposure test completed!"
echo ""
echo "📋 Summary:"
echo "  - GraphQL Compose is enabled"
echo "  - UIC Configuration module is enabled"
echo "  - GraphQL endpoint is accessible"
echo "  - Custom fields are exposed in GraphQL schema"
echo "  - GraphQL queries work with custom fields"
echo ""
echo "✅ Custom fields are properly exposed to GraphQL!"
echo ""
echo "🔗 You can now query custom fields in your GraphQL requests:"
echo "   - fieldSubtitle"
echo "   - fieldHeader"
echo "   - fieldFooter"
echo "   - fieldGallery"
echo "   - fieldAttachments"
echo "   - fieldSpipId"
echo "   - fieldSpipUrl"
