#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${1:-$(pwd)}"
MANIFEST_PATH="${ROOT_DIR}/public/build/manifest.json"

if [[ ! -f "${MANIFEST_PATH}" ]]; then
  echo "ERROR: Missing Vite manifest at ${MANIFEST_PATH}" >&2
  echo "Make sure Forge deploys the deploy branch with CI-built assets." >&2
  exit 1
fi

if ! grep -q '"resources/js/app.ts"' "${MANIFEST_PATH}"; then
  echo "ERROR: Vite manifest exists but resources/js/app.ts entry is missing." >&2
  exit 1
fi

echo "OK: build artifact check passed (${MANIFEST_PATH})."
