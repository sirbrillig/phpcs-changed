#!/usr/bin/env bash
# benchmark.sh — compare the current branch vs trunk
#
# Usage:
#   ./benchmark.sh
#
# Environment variables (all optional):
#   N_FILES=10   number of changed PHP files to scan
#   RUNS=10      hyperfine measurement runs
#   WARMUP=2     hyperfine warmup runs
#
# Artifacts left in the repo root (not tracked by git):
#   .bench-trunk/          git worktree for trunk (reused on subsequent runs)
#   benchmark-results.md   hyperfine markdown export

set -euo pipefail

# ── Paths ──────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CURRENT_BIN="$SCRIPT_DIR/bin/phpcs-changed"
TRUNK_WORKTREE="$SCRIPT_DIR/.bench-trunk"
TRUNK_BIN="$TRUNK_WORKTREE/bin/phpcs-changed"
PHPCS="$SCRIPT_DIR/vendor/bin/phpcs"
RESULTS_FILE="$SCRIPT_DIR/benchmark-results.md"

# ── Tuneable ───────────────────────────────────────────────────────────────
N_FILES=${N_FILES:-10}
RUNS=${RUNS:-10}
WARMUP=${WARMUP:-2}

# ── Sanity checks ─────────────────────────────────────────────────────────
command -v hyperfine >/dev/null 2>&1 \
  || { echo "ERROR: hyperfine not found. Install with: brew install hyperfine"; exit 1; }
[ -f "$PHPCS" ] \
  || { echo "ERROR: phpcs not found at $PHPCS. Run: composer install"; exit 1; }

# ── 1. Trunk worktree ─────────────────────────────────────────────────────
if [ ! -d "$TRUNK_WORKTREE" ]; then
  echo "→ Creating trunk worktree at $TRUNK_WORKTREE …"
  git -C "$SCRIPT_DIR" worktree add "$TRUNK_WORKTREE" trunk
else
  echo "→ Trunk worktree already exists."
fi

# ── 2. Ephemeral benchmark git repo ───────────────────────────────────────
BENCH_REPO="$(mktemp -d)"
trap 'rm -rf "$BENCH_REPO"' EXIT

git -C "$BENCH_REPO" init -q
git -C "$BENCH_REPO" config user.email "bench@example.com"
git -C "$BENCH_REPO" config user.name "Benchmark"

# Create initial PHP files with PSR2 violations (uppercase TRUE / FALSE).
# Both the committed and staged versions have violations so both builds must
# scan both file versions — this is the worst-case and ensures a fair comparison.
echo "→ Preparing $N_FILES PHP test files …"
FILES=()
for i in $(seq 1 "$N_FILES"); do
  fname="file${i}.php"

  # Committed (HEAD) version ─ existing violations: TRUE, FALSE
  cat > "$BENCH_REPO/$fname" << PHPEOF
<?php
class Foo${i}
{
    public function check(): bool
    {
        \$a = TRUE;
        return \$a;
    }

    public function invert(): bool
    {
        \$b = FALSE;
        return !\$b;
    }
}
PHPEOF

  FILES+=("$fname")
  git -C "$BENCH_REPO" add "$fname"
done
git -C "$BENCH_REPO" commit -q -m "initial commit"

# Staged version ─ add a new violation to each file
for i in $(seq 1 "$N_FILES"); do
  fname="file${i}.php"
  cat >> "$BENCH_REPO/$fname" << PHPEOF

function helper${i}(): void
{
    \$v = NULL;
    echo \$v;
}
PHPEOF
  git -C "$BENCH_REPO" add "$fname"
done

# ── 3. Run hyperfine ──────────────────────────────────────────────────────
FILES_STR="${FILES[*]}"
CURRENT_BRANCH="$(git -C "$SCRIPT_DIR" branch --show-current)"
CURRENT_SHA="$(git -C "$SCRIPT_DIR" rev-parse --short HEAD)"
TRUNK_SHA="$(git -C "$TRUNK_WORKTREE" rev-parse --short HEAD)"

CURRENT_CMD="php '$CURRENT_BIN' --git-staged --phpcs-path='$PHPCS' --standard=PSR2 --always-exit-zero $FILES_STR"
TRUNK_CMD="php '$TRUNK_BIN' --git-staged --phpcs-path='$PHPCS' --standard=PSR2 --always-exit-zero $FILES_STR"

echo ""
printf "Benchmark: %d staged PHP files, --standard=PSR2\n" "$N_FILES"
printf "  %-30s  %s\n" "$CURRENT_BRANCH" "$CURRENT_SHA"
printf "  %-30s  %s\n" "trunk" "$TRUNK_SHA"
echo ""

cd "$BENCH_REPO"
# shellcheck disable=SC2086
hyperfine \
  --warmup "$WARMUP" \
  --runs   "$RUNS" \
  -n "$CURRENT_BRANCH ($CURRENT_SHA)" \
  -n "trunk ($TRUNK_SHA)" \
  "$CURRENT_CMD" \
  "$TRUNK_CMD" \
  --export-markdown "$RESULTS_FILE"

echo ""
echo "Results written to $RESULTS_FILE"
