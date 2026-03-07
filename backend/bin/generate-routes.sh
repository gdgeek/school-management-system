#!/usr/bin/env bash
# PSR-15 Route Generator — shell wrapper
#
# Usage:
#   ./bin/generate-routes.sh <resource> [options]
#
# Delegates all arguments to the PHP script located in the same directory.
# Resolves the backend root automatically so the script can be called from
# any working directory.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="${SCRIPT_DIR}/generate-routes.php"

if [[ ! -f "${PHP_SCRIPT}" ]]; then
    echo "Error: generate-routes.php not found at ${PHP_SCRIPT}" >&2
    exit 1
fi

exec php "${PHP_SCRIPT}" "$@"
