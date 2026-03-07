#!/usr/bin/env bash
# Shell wrapper for the route constants generator.
# Usage: bin/generate-route-constants.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

php "${SCRIPT_DIR}/generate-route-constants.php" "$@"
