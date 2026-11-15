#!/usr/bin/env bash
# Build a clean distribution ZIP for Apogee Watcher (folder name apogee-watcher/).
# Excludes git/IDE/repo docs that must not ship in the plugin package.
#
# Usage:
#   ./bin/build-release-zip.sh [out_dir]
#   ./bin/build-release-zip.sh --wporg [out_dir]   # strips Update URI (required for WordPress.org)
set -euo pipefail

WPORG=0
OUT_DIR=""
for arg in "$@"; do
  case "$arg" in
    --wporg) WPORG=1 ;;
    *) OUT_DIR="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${OUT_DIR:-$ROOT/dist}"
VERSION="$(grep -E "^\s*\*\s*Version:" "$ROOT/apogee-watcher.php" | head -1 | awk '{print $NF}')"
STAGE="$(mktemp -d)"
DEST="$STAGE/apogee-watcher"

mkdir -p "$OUT_DIR" "$DEST"

rsync -a \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='.DS_Store' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='.cursor/' \
  --exclude='*.zip' \
  --exclude='dist/' \
  --exclude='bin/' \
  --exclude='CONTRIBUTING.md' \
  --exclude='.gitattributes' \
  "$ROOT/" "$DEST/"

if [[ "$WPORG" -eq 1 ]]; then
  # WordPress.org forbids Update URI / third-party updaters in directory plugins.
  perl -i -ne 'print unless /^\s*\*\s*Update URI:/' "$DEST/apogee-watcher.php"
  echo "WPORG=1 (Update URI stripped)"
fi

SUFFIX=""
if [[ "$WPORG" -eq 1 ]]; then
  SUFFIX="-wporg"
fi

ZIP="$OUT_DIR/apogee-watcher-${VERSION}${SUFFIX}.zip"
rm -f "$ZIP"
(
  cd "$STAGE"
  zip -rq "$ZIP" apogee-watcher
)

SHA="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
rm -rf "$STAGE"

echo "ZIP=$ZIP"
echo "SHA256=$SHA"
echo "VERSION=$VERSION"
