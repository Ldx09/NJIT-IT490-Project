#!/bin/bash

FRONTEND_VM_USER="ubuntu"
FRONTEND_VM_IP="10.246.134.210"
FRONTEND_VM_PATH="/var/www/html"

BACKEND_VM_USER="ubuntu"
BACKEND_VM_IP="10.246.134.189"
BACKEND_VM_PATH="/var/www/html"

DMZ_VM_USER="ubuntu"
DMZ_VM_IP="10.246.134.18"
DMZ_VM_PATH="/etc/dmz"

DEPLOY_VM_USER="ubuntu"
DEPLOY_VM_IP="10.246.134.46"
DEPLOY_VM_PATH="/srv/deploy/bundles"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
VERSION="v${TIMESTAMP}"
BUNDLE_NAME="it490_${VERSION}.tgz"
STAGING_DIR="/tmp/it490_staging_${VERSION}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILDS_DIR="${SCRIPT_DIR}/builds"
MANIFESTS_DIR="${SCRIPT_DIR}/manifests"

mkdir -p "$STAGING_DIR/frontend"
mkdir -p "$STAGING_DIR/backend"
mkdir -p "$STAGING_DIR/dmz"
mkdir -p "$BUILDS_DIR"
mkdir -p "$MANIFESTS_DIR"

echo "Pulling files from frontend VM..."
scp -r ${FRONTEND_VM_USER}@${FRONTEND_VM_IP}:${FRONTEND_VM_PATH}/. "$STAGING_DIR/frontend/"
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to pull files from frontend VM."
    exit 1
fi

echo "Pulling files from backend VM..."
scp -r ${BACKEND_VM_USER}@${BACKEND_VM_IP}:${BACKEND_VM_PATH}/. "$STAGING_DIR/backend/"
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to pull files from backend VM."
    exit 1
fi

echo "Pulling files from DMZ VM..."
scp -r ${DMZ_VM_USER}@${DMZ_VM_IP}:${DMZ_VM_PATH}/. "$STAGING_DIR/dmz/"
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to pull files from DMZ VM."
    exit 1
fi

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
        },
        {
            "role": "dmz",
            "files": [
                {
                    "source": "dmz/",
                    "destination": "/etc/dmz/"
                }
            ],
            "service_restart": "nginx"
        }
    ]
}
EOF

cp "$STAGING_DIR/manifest.json" "$MANIFESTS_DIR/manifest_${VERSION}.json"

cd /tmp
tar -czf "$BUILDS_DIR/$BUNDLE_NAME" "it490_staging_${VERSION}"

echo "Verifying bundle..."
tar -tzf "$BUILDS_DIR/$BUNDLE_NAME" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: Bundle verification failed."
    exit 1
fi

echo "Pushing bundle to Deploy VM..."
scp "$BUILDS_DIR/$BUNDLE_NAME" ${DEPLOY_VM_USER}@${DEPLOY_VM_IP}:${DEPLOY_VM_PATH}/
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to push bundle to Deploy VM."
    exit 1
fi

scp "$MANIFESTS_DIR/manifest_${VERSION}.json" ${DEPLOY_VM_USER}@${DEPLOY_VM_IP}:${DEPLOY_VM_PATH}/
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to push manifest to Deploy VM."
    exit 1
fi

rm -rf "$STAGING_DIR"

echo "Build complete: $BUNDLE_NAME"
echo "Version: $VERSION"
echo "Bundle pushed to Deploy VM at ${DEPLOY_VM_IP}:${DEPLOY_VM_PATH}"
