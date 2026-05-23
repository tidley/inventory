#!/bin/bash

set -euo pipefail

cd "$(dirname "$0")/.."

BUILD_DIR="build/inventory"
rm -rf "$BUILD_DIR" build/inventory.zip

mkdir -p "$BUILD_DIR"

cp .htaccess "$BUILD_DIR/"
cp .env.example "$BUILD_DIR/"
cp index.php api.php lib.php version.php updater.php photo.php test-db.php "$BUILD_DIR/"
cp styles.css app.js manifest.json sw.js favicon.ico "$BUILD_DIR/"
cp -R icons "$BUILD_DIR/"

cd build
zip -r inventory.zip inventory/

echo "Release build: build/inventory.zip"
