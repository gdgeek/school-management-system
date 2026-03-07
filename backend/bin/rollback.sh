#!/usr/bin/env bash
# PSR-15 Migration Rollback Script
#
# Usage:
#   ./bin/rollback.sh [commit-hash]
#
# If no commit hash is provided, shows recent git log and prompts for input.
# See docs/rollback-procedure.md for full procedure documentation.

set -euo pipefail

# ---------------------------------------------------------------------------
# Color helpers
# ---------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; }
die()     { error "$*"; exit 1; }

# ---------------------------------------------------------------------------
# Resolve paths
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
# Repo root is two levels above backend (backend/ → school-management-system/ → repo root)
REPO_ROOT="$(cd "${BACKEND_DIR}/../.." && pwd)"

CONTAINER_NAME="xrugc-school-backend"
HEALTH_URL="http://localhost:8084/api/health"
INDEX_PHP_REL="school-management-system/backend/public/index.php"
CONFIG_DIR_REL="school-management-system/backend/config"

# ---------------------------------------------------------------------------
# Pre-flight checks
# ---------------------------------------------------------------------------
preflight_checks() {
    info "Running pre-flight checks..."

    # git
    if ! command -v git &>/dev/null; then
        die "git is not available. Please install git and try again."
    fi
    success "git found: $(git --version)"

    # docker
    if ! command -v docker &>/dev/null; then
        die "docker is not available. Please install Docker and try again."
    fi
    success "docker found: $(docker --version | head -1)"

    # container exists
    if ! docker inspect "${CONTAINER_NAME}" &>/dev/null; then
        die "Docker container '${CONTAINER_NAME}' does not exist. Is the stack running?"
    fi
    success "Container '${CONTAINER_NAME}' exists."

    # we are inside a git repo
    if ! git -C "${REPO_ROOT}" rev-parse --git-dir &>/dev/null; then
        die "Not inside a git repository (checked: ${REPO_ROOT})."
    fi
    success "Git repository found at: ${REPO_ROOT}"
}

# ---------------------------------------------------------------------------
# Resolve commit hash
# ---------------------------------------------------------------------------
resolve_commit() {
    local provided_hash="${1:-}"

    if [[ -n "${provided_hash}" ]]; then
        TARGET_COMMIT="${provided_hash}"
    else
        echo ""
        info "No commit hash provided. Showing recent git log for:"
        echo -e "  ${BOLD}${INDEX_PHP_REL}${RESET}"
        echo ""
        git -C "${REPO_ROOT}" log --oneline -20 -- "${INDEX_PHP_REL}" || \
            git -C "${REPO_ROOT}" log --oneline -20
        echo ""
        read -rp "$(echo -e "${CYAN}Enter the target commit hash to roll back to:${RESET} ")" TARGET_COMMIT
        echo ""
    fi

    if [[ -z "${TARGET_COMMIT}" ]]; then
        die "No commit hash provided. Aborting."
    fi

    # Verify the commit exists
    if ! git -C "${REPO_ROOT}" cat-file -e "${TARGET_COMMIT}^{commit}" 2>/dev/null; then
        die "Commit '${TARGET_COMMIT}' does not exist in git history. Aborting."
    fi

    # Resolve to full hash for display
    FULL_COMMIT="$(git -C "${REPO_ROOT}" rev-parse "${TARGET_COMMIT}")"
    COMMIT_MSG="$(git -C "${REPO_ROOT}" log --format='%s' -1 "${FULL_COMMIT}")"
    success "Target commit resolved: ${FULL_COMMIT:0:12} — ${COMMIT_MSG}"
}

# ---------------------------------------------------------------------------
# Confirmation prompt
# ---------------------------------------------------------------------------
confirm_rollback() {
    echo ""
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo -e "${YELLOW}  ROLLBACK CONFIRMATION${RESET}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo ""
    echo -e "  Target commit : ${BOLD}${FULL_COMMIT:0:12}${RESET} — ${COMMIT_MSG}"
    echo -e "  Files to revert:"
    echo -e "    • ${INDEX_PHP_REL}"
    if [[ "${ROLLBACK_CONFIG}" == "yes" ]]; then
        echo -e "    • ${CONFIG_DIR_REL}/"
    fi
    echo -e "  Container     : ${BOLD}${CONTAINER_NAME}${RESET} (will be restarted)"
    echo ""
    echo -e "${YELLOW}  This will create a rollback branch and restore the above files.${RESET}"
    echo ""
    read -rp "$(echo -e "${BOLD}Proceed with rollback? [y/N]:${RESET} ")" CONFIRM
    echo ""

    if [[ ! "${CONFIRM}" =~ ^[Yy]$ ]]; then
        warn "Rollback cancelled by user."
        exit 0
    fi
}

# ---------------------------------------------------------------------------
# Ask about config rollback
# ---------------------------------------------------------------------------
ask_config_rollback() {
    echo ""
    warn "Do you also want to revert ${CONFIG_DIR_REL}/ (routes, middleware, di)?"
    warn "Only do this if the config files were changed as part of the PSR-15 migration."
    echo ""
    read -rp "$(echo -e "${CYAN}Revert config/ directory? [y/N]:${RESET} ")" CONFIG_ANSWER
    echo ""

    if [[ "${CONFIG_ANSWER}" =~ ^[Yy]$ ]]; then
        ROLLBACK_CONFIG="yes"
        info "Config directory will also be reverted."
    else
        ROLLBACK_CONFIG="no"
        info "Config directory will NOT be reverted."
    fi
}

# ---------------------------------------------------------------------------
# Execute rollback
# ---------------------------------------------------------------------------
execute_rollback() {
    local branch_name="rollback/psr15-$(date +%Y%m%d-%H%M)"

    info "Creating rollback branch: ${branch_name}"
    git -C "${REPO_ROOT}" checkout -b "${branch_name}"
    success "Branch created: ${branch_name}"

    info "Restoring ${INDEX_PHP_REL} from commit ${FULL_COMMIT:0:12}..."
    git -C "${REPO_ROOT}" checkout "${FULL_COMMIT}" -- "${INDEX_PHP_REL}"
    success "index.php restored."

    if [[ "${ROLLBACK_CONFIG}" == "yes" ]]; then
        info "Restoring ${CONFIG_DIR_REL}/ from commit ${FULL_COMMIT:0:12}..."
        git -C "${REPO_ROOT}" checkout "${FULL_COMMIT}" -- "${CONFIG_DIR_REL}"
        success "config/ restored."
    fi

    info "Committing rollback..."
    git -C "${REPO_ROOT}" add "${INDEX_PHP_REL}"
    if [[ "${ROLLBACK_CONFIG}" == "yes" ]]; then
        git -C "${REPO_ROOT}" add "${CONFIG_DIR_REL}"
    fi
    git -C "${REPO_ROOT}" commit -m "rollback: revert PSR-15 migration to ${FULL_COMMIT:0:12}

Rolled back files:
  - ${INDEX_PHP_REL}$([ "${ROLLBACK_CONFIG}" == "yes" ] && echo "
  - ${CONFIG_DIR_REL}/")

Original commit: ${FULL_COMMIT}
Original message: ${COMMIT_MSG}
Rollback timestamp: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"

    success "Rollback committed on branch: ${branch_name}"

    info "Restarting Docker container: ${CONTAINER_NAME}..."
    docker restart "${CONTAINER_NAME}"
    success "Container restart initiated."
}

# ---------------------------------------------------------------------------
# Verify rollback — poll health endpoint
# ---------------------------------------------------------------------------
verify_rollback() {
    local max_wait=30
    local interval=2
    local elapsed=0
    local http_code=""

    info "Waiting for container to become healthy (up to ${max_wait}s)..."
    echo ""

    while [[ ${elapsed} -lt ${max_wait} ]]; do
        sleep "${interval}"
        elapsed=$((elapsed + interval))

        http_code="$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 "${HEALTH_URL}" 2>/dev/null || echo "000")"

        if [[ "${http_code}" == "200" ]]; then
            success "Health check passed (HTTP ${http_code}) after ${elapsed}s."
            echo ""
            info "Health endpoint response:"
            curl -s --max-time 5 "${HEALTH_URL}" | python3 -m json.tool 2>/dev/null || \
                curl -s --max-time 5 "${HEALTH_URL}"
            echo ""
            return 0
        else
            echo -ne "  ${elapsed}s — HTTP ${http_code} — waiting...\r"
        fi
    done

    echo ""
    error "Health check did NOT return 200 after ${max_wait}s (last code: ${http_code})."
    error "Check container logs:"
    echo ""
    echo -e "  ${BOLD}docker logs ${CONTAINER_NAME} --tail 50${RESET}"
    echo ""
    return 1
}

# ---------------------------------------------------------------------------
# Post-rollback instructions
# ---------------------------------------------------------------------------
post_rollback_instructions() {
    echo ""
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo -e "${GREEN}  ROLLBACK COMPLETE${RESET}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo ""
    echo -e "${BOLD}Next steps:${RESET}"
    echo ""
    echo -e "  1. ${BOLD}Monitor logs${RESET} for the next 30 minutes:"
    echo -e "     docker logs -f ${CONTAINER_NAME} 2>&1 | grep -i 'error\\|fatal\\|exception'"
    echo ""
    echo -e "  2. ${BOLD}Verify core endpoints${RESET} manually:"
    echo -e "     curl -s -X POST http://localhost:8084/api/auth/login \\"
    echo -e "       -H 'Content-Type: application/json' \\"
    echo -e "       -d '{\"username\":\"guanfei\",\"password\":\"123456\"}'"
    echo ""
    echo -e "  3. ${BOLD}Notify the team${RESET} using the template in:"
    echo -e "     school-management-system/backend/docs/rollback-procedure.md (Section 5)"
    echo ""
    echo -e "  4. ${BOLD}Investigate root cause${RESET} before re-deploying:"
    echo -e "     docker logs ${CONTAINER_NAME} --since '<incident-start-time>' > /tmp/incident-logs.txt"
    echo ""
    echo -e "  5. ${BOLD}Flush Redis cache${RESET} only if cache-related issues are suspected:"
    echo -e "     docker exec -it xrugc-redis redis-cli FLUSHDB"
    echo ""
    echo -e "${YELLOW}  See full procedure: school-management-system/backend/docs/rollback-procedure.md${RESET}"
    echo ""
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
    echo ""
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo -e "${BOLD}  PSR-15 Migration Rollback Script${RESET}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo ""

    preflight_checks
    echo ""

    resolve_commit "${1:-}"

    ask_config_rollback

    confirm_rollback

    execute_rollback

    if verify_rollback; then
        post_rollback_instructions
        exit 0
    else
        echo ""
        error "Rollback may have issues — health check failed."
        error "The git changes were committed and the container was restarted."
        error "Please check the container logs and verify manually."
        echo ""
        post_rollback_instructions
        exit 1
    fi
}

main "$@"
