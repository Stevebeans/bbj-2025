#!/bin/bash
# Deploy bbj-v2-theme to production or staging.
# Usage: bash .claude/scripts/deploy-theme.sh [--staging]
#
# Builds compiled CSS (npm run build) before transferring so the server
# doesn't need a node toolchain. Excludes node_modules, src/, .git etc.

set -e

LOCAL_THEME="/c/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme"
PROD_PATH="/home/1358704.cloudwaysapps.com/duesaptjae/public_html/wp-content/themes/bbj-v2-theme"
STAGING_PATH="/home/1358704.cloudwaysapps.com/ftgtnduhbt/public_html/wp-content/themes/bbj-v2-theme"

TARGET="bbj-prod"
REMOTE_PATH="$PROD_PATH"
LABEL="PRODUCTION"

if [ "$1" == "--staging" ]; then
    TARGET="bbj-staging"
    REMOTE_PATH="$STAGING_PATH"
    LABEL="STAGING"
fi
echo "Deploying theme to $LABEL..."

if [ ! -d "$LOCAL_THEME" ]; then
    echo "Error: Local theme not found at $LOCAL_THEME"
    exit 1
fi

# Build compiled CSS so the server doesn't need node.
echo "Building Tailwind..."
(cd "$LOCAL_THEME" && npm run build --silent)

echo "Creating archive..."
cd "$LOCAL_THEME"
tar -cf /tmp/bbj-v2-theme.tar \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='src/css' \
    --exclude='.DS_Store' \
    --exclude='.claude' \
    --exclude='package-lock.json' \
    --exclude='postcss.config.js' \
    --exclude='tailwind.config.js' \
    .
gzip -f /tmp/bbj-v2-theme.tar

echo "Transferring to server..."
scp /tmp/bbj-v2-theme.tar.gz "${TARGET}:~/bbj-v2-theme.tar.gz"

echo "Extracting on server..."
ssh "${TARGET}" "mkdir -p ${REMOTE_PATH} && cd ${REMOTE_PATH} && rm -rf build inc template-parts assets *.php style.css && mkdir -p .theme-extract && tar -xzf ~/bbj-v2-theme.tar.gz -C .theme-extract && cp -rf .theme-extract/. . && rm -rf .theme-extract ~/bbj-v2-theme.tar.gz"

rm /tmp/bbj-v2-theme.tar.gz

echo ""
echo "Theme deployment complete!"
echo "Target: $TARGET"
echo "Path: $REMOTE_PATH"
