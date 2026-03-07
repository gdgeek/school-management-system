#!/usr/bin/env bash
# =============================================================================
# PSR-15 Migration Benchmark Runner
#
# Wraps benchmark.php with sensible defaults and optional "before/after"
# comparison mode.
#
# Usage:
#   ./run-benchmark.sh                          # 100 iterations, localhost:8084
#   ./run-benchmark.sh 50                       # 50 iterations
#   ./run-benchmark.sh 100 http://host:8084     # custom URL
#   ./run-benchmark.sh --compare                # diff two saved JSON reports
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="${SCRIPT_DIR}/results"
ITERATIONS="${1:-100}"
BASE_URL="${2:-http://localhost:8084}"

# ── Colour helpers ────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

# ── Dependency check ──────────────────────────────────────────────────────────
if ! command -v php &>/dev/null; then
  echo -e "${RED}Error: php is not installed or not in PATH.${NC}" >&2
  exit 1
fi

if ! command -v curl &>/dev/null; then
  echo -e "${RED}Error: curl is not installed or not in PATH.${NC}" >&2
  exit 1
fi

# ── Compare mode ──────────────────────────────────────────────────────────────
if [[ "${1:-}" == "--compare" ]]; then
  echo -e "${BOLD}${CYAN}═══ Benchmark Comparison Mode ═══${NC}"

  mapfile -t reports < <(ls -t "${RESULTS_DIR}"/benchmark-*.json 2>/dev/null | head -2)

  if [[ ${#reports[@]} -lt 2 ]]; then
    echo -e "${YELLOW}Need at least 2 saved benchmark reports to compare.${NC}"
    echo "Run the benchmark twice (before and after a change) first."
    exit 1
  fi

  newer="${reports[0]}"
  older="${reports[1]}"

  echo -e "  Older : ${older}"
  echo -e "  Newer : ${newer}"
  echo ""

  # Use PHP to parse and diff the two JSON files
  php - "$older" "$newer" <<'PHP'
<?php
[$older, $newer] = [$argv[1], $argv[2]];

$a = json_decode(file_get_contents($older), true)['results'] ?? [];
$b = json_decode(file_get_contents($newer), true)['results'] ?? [];

$C = [
    'reset'  => "\033[0m",
    'green'  => "\033[0;32m",
    'red'    => "\033[0;31m",
    'yellow' => "\033[1;33m",
    'bold'   => "\033[1m",
    'white'  => "\033[1;37m",
];

echo $C['bold'] . $C['white'];
printf("  %-42s  %10s  %10s  %10s\n", 'Endpoint', 'Old avg', 'New avg', 'Delta');
echo str_repeat('─', 80) . $C['reset'] . "\n";

foreach ($b as $label => $new) {
    $old = $a[$label] ?? null;
    if ($old === null) {
        printf("  %-42s  %10s  %10.1fms  %s\n", $label, 'N/A', $new['avg'], '(new)');
        continue;
    }
    $delta = $new['avg'] - $old['avg'];
    $pct   = $old['avg'] > 0 ? ($delta / $old['avg']) * 100 : 0;
    $color = $delta <= 0 ? $C['green'] : ($delta < 50 ? $C['yellow'] : $C['red']);
    $sign  = $delta >= 0 ? '+' : '';
    printf(
        "  %-42s  %8.1fms  %8.1fms  %s%s%.1fms (%s%.1f%%)%s\n",
        $label,
        $old['avg'],
        $new['avg'],
        $color, $sign, $delta,
        $sign, $pct,
        $C['reset']
    );
}
echo "\n";
PHP

  exit 0
fi

# ── Normal benchmark run ───────────────────────────────────────────────────────
echo -e "${BOLD}${CYAN}═══ PSR-15 Benchmark ═══${NC}"
echo -e "  Iterations : ${ITERATIONS}"
echo -e "  Base URL   : ${BASE_URL}"
echo ""

# Verify the API is reachable before wasting time
if ! curl -sf --max-time 5 "${BASE_URL}/health" -o /dev/null 2>/dev/null; then
  echo -e "${YELLOW}⚠  Warning: ${BASE_URL}/health did not respond. The server may be down.${NC}"
  echo -e "   Continuing anyway — individual endpoint results may show failures.\n"
fi

php "${SCRIPT_DIR}/benchmark.php" "${ITERATIONS}" "${BASE_URL}"

echo -e "${GREEN}Done.${NC}"
