#!/bin/bash
# Deploy a BBJ plugin to staging or production.
#
# Usage:
#   bash .claude/scripts/deploy-plugin.sh                    # deploys ALL plugins to PROD
#   bash .claude/scripts/deploy-plugin.sh --staging          # deploys ALL plugins to STAGING
#   bash .claude/scripts/deploy-plugin.sh bbj-v2 --staging   # deploys ONE plugin to STAGING
#
# Flags can come in any order. Plugin slug must match a directory name under
# wp-content/plugins/ AND have an entry in the CLEANUP map below.

set -e

PLUGINS_DIR="/c/xampp/htdocs/bbj/wp-content/plugins"
PROD_BASE="/home/1620468.cloudwaysapps.com/duesaptjae/public_html/wp-content/plugins"
STAGING_BASE="/home/1620468.cloudwaysapps.com/ftgtnduhbt/public_html/wp-content/plugins"

# Per-plugin cleanup: top-level paths nuked on the server before fresh extract.
# Keep these tight — anything not listed here will linger if you delete it locally.
declare -A CLEANUP=(
    [bigbrotherjunkies-data]="src vendor assets build"
    [bbj-v2]="includes assets build src"
)

# Plugins to deploy when no slug arg is passed.
DEFAULT_PLUGINS=(bigbrotherjunkies-data bbj-v2)

deploy_one() {
    local PLUGIN_SLUG="$1"
    local TARGET="$2"
    local REMOTE_BASE="$3"
    local LOCAL_PLUGIN="$PLUGINS_DIR/$PLUGIN_SLUG"
    local REMOTE_PATH="$REMOTE_BASE/$PLUGIN_SLUG"
    local CLEANUP_DIRS="${CLEANUP[$PLUGIN_SLUG]}"

    if [ -z "$CLEANUP_DIRS" ]; then
        echo "Error: Unknown plugin '$PLUGIN_SLUG' (add it to the CLEANUP map in this script)"
        return 1
    fi
    if [ ! -d "$LOCAL_PLUGIN" ]; then
        echo "Error: Local plugin not found at $LOCAL_PLUGIN"
        return 1
    fi

    echo ""
    echo "==> $PLUGIN_SLUG"
    cd "$LOCAL_PLUGIN"
    tar -cf /tmp/plugin.tar \
        --exclude='node_modules' \
        --exclude='.git' \
        --exclude='.DS_Store' \
        --exclude='.claude' \
        --exclude='do-not-upload' \
        --exclude='do_not_upload' \
        .
    gzip -f /tmp/plugin.tar
    scp /tmp/plugin.tar.gz ${TARGET}:~/plugin.tar.gz
    ssh ${TARGET} "cd ${REMOTE_PATH} && rm -rf $CLEANUP_DIRS && mkdir -p .plugin-extract && tar -xzf ~/plugin.tar.gz -C .plugin-extract && cp -rf .plugin-extract/. . && rm -rf .plugin-extract ~/plugin.tar.gz"
    rm /tmp/plugin.tar.gz
    echo "    Done: $REMOTE_PATH"
}

# Parse args (flags can be in any order)
TARGET="bbj-prod"
REMOTE_BASE="$PROD_BASE"
PLUGIN_ARG=""

for arg in "$@"; do
    case "$arg" in
        --staging) TARGET="bbj-staging"; REMOTE_BASE="$STAGING_BASE" ;;
        --prod|--production) TARGET="bbj-prod"; REMOTE_BASE="$PROD_BASE" ;;
        *) PLUGIN_ARG="$arg" ;;
    esac
done

if [ "$TARGET" == "bbj-staging" ]; then
    echo "=== Deploying to STAGING ==="
else
    echo "=== Deploying to PRODUCTION ==="
fi

if [ -n "$PLUGIN_ARG" ]; then
    deploy_one "$PLUGIN_ARG" "$TARGET" "$REMOTE_BASE"
else
    for slug in "${DEFAULT_PLUGINS[@]}"; do
        deploy_one "$slug" "$TARGET" "$REMOTE_BASE"
    done
fi

echo ""
echo "All deployments complete!"
