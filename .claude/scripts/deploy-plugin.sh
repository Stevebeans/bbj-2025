#!/bin/bash
# Deploy bigbrotherjunkies-data plugin to production
# Usage: bash .claude/scripts/deploy-plugin.sh [--staging]

set -e

# Paths
LOCAL_PLUGIN="/c/xampp/htdocs/bbj/wp-content/plugins/bigbrotherjunkies-data"
PROD_PATH="/home/1358704.cloudwaysapps.com/duesaptjae/public_html/wp-content/plugins/bigbrotherjunkies-data"
STAGING_PATH="/home/1358704.cloudwaysapps.com/ftgtnduhbt/public_html/wp-content/plugins/bigbrotherjunkies-data"

# Default to production
TARGET="bbj-prod"
REMOTE_PATH="$PROD_PATH"

# Check for staging flag
if [ "$1" == "--staging" ]; then
    TARGET="bbj-staging"
    REMOTE_PATH="$STAGING_PATH"
    echo "Deploying to STAGING..."
else
    echo "Deploying to PRODUCTION..."
fi

# Verify local plugin exists
if [ ! -d "$LOCAL_PLUGIN" ]; then
    echo "Error: Local plugin not found at $LOCAL_PLUGIN"
    exit 1
fi

# Create archive (exclude node_modules, .git, etc)
echo "Creating archive..."
cd "$LOCAL_PLUGIN"
tar -cf /tmp/plugin.tar --exclude='node_modules' --exclude='.git' --exclude='.DS_Store' --exclude='.claude' --exclude='do-not-upload' .
gzip -f /tmp/plugin.tar

# Transfer to server
echo "Transferring to server..."
scp /tmp/plugin.tar.gz ${TARGET}:~/plugin.tar.gz

# Extract on server (replace old files)
echo "Extracting on server..."
ssh ${TARGET} "cd ${REMOTE_PATH} && rm -rf src vendor assets build && mkdir -p .plugin-extract && tar -xzf ~/plugin.tar.gz -C .plugin-extract && cp -rf .plugin-extract/. . && rm -rf .plugin-extract ~/plugin.tar.gz"

# Cleanup local
rm /tmp/plugin.tar.gz

echo ""
echo "Deployment complete!"
echo "Target: $TARGET"
echo "Path: $REMOTE_PATH"
