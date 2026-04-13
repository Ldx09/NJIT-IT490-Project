#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: ./build_bundle.sh <version_number>"
    exit 1
fi

VERSION="$1"
BUNDLE_NAME="it490_v${VERSION}.tgz"
STAGING_DIR="/tmp/it490_staging_v${VERSION}"
APP_SRC="$(cd "$(dirname "$0")/app-src" && pwd)"
BUILDS_DIR="$(cd "$(dirname "$0")/builds" && pwd)"
MANIFESTS_DIR="$(cd "$(dirname "$0")/manifests" && pwd)"
DEPLOY_VM_DIR="/srv/deploy/bundles"

rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR/frontend"
mkdir -p "$STAGING_DIR/backend"

cp -r "$APP_SRC/frontend/." "$STAGING_DIR/frontend/"
cp -r "$APP_SRC/backend/." "$STAGING_DIR/backend/"

cat > "$STAGING_DIR/manifest.json" <<EOF
{
    "version": "${VERSION}",
    "bundle_name": "${BUNDLE_NAME}",
    "targets": [
        {
            "role": "frontend",
            "files": [
                {
                    "source": "frontend/index.html",
                    "destination": "/var/www/html/index.html"
                }
            ],
            "service_restart": "apache2"
        },
        {
            "role": "backend",
            "files": [
                {
                    "source": "backend/api.php",
                    "destination": "/var/www/html/api.php"
                }
            ],
            "service_restart": "apache2"
        }
    ]
}
EOF

cp "$STAGING_DIR/manifest.json" "$MANIFESTS_DIR/manifest_v${VERSION}.json"

cd /tmp
tar -czf "$BUILDS_DIR/$BUNDLE_NAME" "it490_staging_v${VERSION}"

echo "Verifying bundle..."
tar -tzf "$BUILDS_DIR/$BUNDLE_NAME" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: Bundle verification failed."
    exit 1
fi

if [ -d "$DEPLOY_VM_DIR" ]; then
    cp "$BUILDS_DIR/$BUNDLE_NAME" "$DEPLOY_VM_DIR/"
    cp "$MANIFESTS_DIR/manifest_v${VERSION}.json" "$DEPLOY_VM_DIR/"
    echo "Bundle and manifest copied to Deploy VM at $DEPLOY_VM_DIR"
else
    echo "WARNING: Deploy VM directory $DEPLOY_VM_DIR not found. Bundle saved locally to $BUILDS_DIR"
fi

rm -rf "$STAGING_DIR"

echo "Build complete: $BUNDLE_NAME"
