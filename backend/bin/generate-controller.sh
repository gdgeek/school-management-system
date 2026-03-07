#!/usr/bin/env bash
# PSR-15 Controller Generator — shell wrapper
# Usage: ./bin/generate-controller.sh <Name> [--with-service] [--force]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="${SCRIPT_DIR}/generate-controller.php"

if ! command -v php &>/dev/null; then
    echo "Error: php is not available in PATH." >&2
    exit 1
fi

exec php "${PHP_SCRIPT}" "$@"
