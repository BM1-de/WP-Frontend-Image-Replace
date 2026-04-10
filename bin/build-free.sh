#!/usr/bin/env bash
#
# Build the free version of BM1 Frontend Image Replace.
# Removes all __premium_only files and //#! pro ... //#! endpro code blocks.
#
# Usage: bash bin/build-free.sh [output-dir]
#   Default output: /tmp/bm1-frontend-image-replace

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
OUTPUT_DIR="${1:-/tmp/bm1-frontend-image-replace}"

echo "=== Building Free Version ==="
echo "Source:  $PLUGIN_DIR"
echo "Output:  $OUTPUT_DIR"

# Clean previous build.
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

# Copy source (exclude dev files).
rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='bin' \
  "$PLUGIN_DIR/" "$OUTPUT_DIR/"

# Remove premium-only files.
find "$OUTPUT_DIR" -name '*__premium_only.*' -delete
echo "Removed __premium_only files."

# Remove premium code blocks (//#! pro ... //#! endpro).
find "$OUTPUT_DIR" -type f \( -name '*.php' -o -name '*.js' \) -print0 | \
  xargs -0 perl -i -0pe 's{^\s*//\s*#!\s*pro\b.*?//\s*#!\s*endpro\s*\n}{}gms'
echo "Removed premium code blocks."

# Verify: no premium markers or references left.
# Check for leftover markers (exclude freemius SDK and file_exists/require references).
LEFTOVER=$(grep -rn --exclude-dir=freemius '#!\s*pro\|#!\s*endpro' "$OUTPUT_DIR" || true)
if [ -n "$LEFTOVER" ]; then
  echo "WARNING: Leftover premium markers found:"
  echo "$LEFTOVER"
  exit 1
fi
echo "Verification passed: no premium markers remaining."

# Create ZIP.
ZIP_PATH="$(dirname "$OUTPUT_DIR")/bm1-frontend-image-replace.zip"
cd "$(dirname "$OUTPUT_DIR")"
rm -f "$ZIP_PATH"
zip -r "$ZIP_PATH" "$(basename "$OUTPUT_DIR")/" -x '*.DS_Store'
echo "ZIP created: $ZIP_PATH"
echo "=== Done ==="
