#!/usr/bin/env bash
# benchmark.sh — compare candidate branches vs trunk
#
# Usage:
#   ./benchmark.sh
#
# Environment variables (all optional):
#   N_FILES=10              number of changed PHP files to scan
#   LINES_PER_FILE=14       approximate target line count per file (committed version)
#   RUNS=10                 hyperfine measurement runs
#   WARMUP=2                hyperfine warmup runs
#   CANDIDATES="<branches>" space-separated branches to compare against trunk.
#                           Defaults to the currently checked-out branch.
#                           Example: CANDIDATES="two-batch-phpcs-invocations batch-phpcs-invocations"
#
# Artifacts left in the repo root (not tracked by git):
#   .bench-trunk/, .bench-<branch>/   git worktrees (reused on subsequent runs)
#   benchmark-results.md              hyperfine markdown export

set -euo pipefail

# ── Paths ──────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TRUNK_WORKTREE="$SCRIPT_DIR/.bench-trunk"
TRUNK_BIN="$TRUNK_WORKTREE/bin/phpcs-changed"
PHPCS="$SCRIPT_DIR/vendor/bin/phpcs"
RESULTS_FILE="$SCRIPT_DIR/benchmark-results.md"

# ── Tuneable ───────────────────────────────────────────────────────────────
N_FILES=${N_FILES:-10}
LINES_PER_FILE=${LINES_PER_FILE:-14}
RUNS=${RUNS:-10}
WARMUP=${WARMUP:-2}
CURRENT_BRANCH_DEFAULT="$(git -C "$SCRIPT_DIR" branch --show-current)"
CANDIDATES=${CANDIDATES:-$CURRENT_BRANCH_DEFAULT}

# ── Sanity checks ─────────────────────────────────────────────────────────
command -v hyperfine >/dev/null 2>&1 \
  || { echo "ERROR: hyperfine not found. Install with: brew install hyperfine"; exit 1; }
[ -f "$PHPCS" ] \
  || { echo "ERROR: phpcs not found at $PHPCS. Run: composer install"; exit 1; }

# ── 1. Worktrees ──────────────────────────────────────────────────────────
ensure_worktree () {
  local branch="$1"
  local path="$2"
  if [ ! -d "$path" ]; then
    echo "→ Creating worktree for '$branch' at $path …"
    # --detach lets us add a worktree even if the branch is checked out elsewhere.
    git -C "$SCRIPT_DIR" worktree add --detach "$path" "$branch"
  else
    echo "→ Worktree for '$branch' already exists at $path."
  fi
}

ensure_worktree "trunk" "$TRUNK_WORKTREE"

CANDIDATE_PATHS=()
CANDIDATE_NAMES=()
for branch in $CANDIDATES; do
  path="$SCRIPT_DIR/.bench-${branch}"
  ensure_worktree "$branch" "$path"
  CANDIDATE_PATHS+=("$path")
  CANDIDATE_NAMES+=("$branch")
done

# ── 2. Ephemeral benchmark git repo ───────────────────────────────────────
BENCH_REPO="$(mktemp -d)"
trap 'rm -rf "$BENCH_REPO"' EXIT

git -C "$BENCH_REPO" init -q
git -C "$BENCH_REPO" config user.email "bench@example.com"
git -C "$BENCH_REPO" config user.name "Benchmark"

# Each method block adds 6 lines and one violation (TRUE/FALSE).
# Class scaffold (header + closer) costs ~5 lines, so derive block count from target.
BLOCKS_PER_FILE=$(( (LINES_PER_FILE - 5) / 6 ))
[ "$BLOCKS_PER_FILE" -lt 2 ] && BLOCKS_PER_FILE=2

# Alternate violations across blocks for variety.
VIOLATIONS=(TRUE FALSE)

echo "→ Preparing $N_FILES PHP test files (~$LINES_PER_FILE lines, $BLOCKS_PER_FILE method blocks each) …"
FILES=()
for i in $(seq 1 "$N_FILES"); do
  fname="file${i}.php"
  fpath="$BENCH_REPO/$fname"

  {
    echo "<?php"
    echo "class Foo${i}"
    echo "{"
    for b in $(seq 1 "$BLOCKS_PER_FILE"); do
      violation=${VIOLATIONS[$(( (b - 1) % ${#VIOLATIONS[@]} ))]}
      printf '    public function method%d(): bool\n' "$b"
      echo  '    {'
      printf '        $v = %s;\n' "$violation"
      echo  '        return $v;'
      echo  '    }'
      echo  ''
    done
    echo "}"
  } > "$fpath"

  FILES+=("$fname")
  git -C "$BENCH_REPO" add "$fname"
done
git -C "$BENCH_REPO" commit -q -m "initial commit"

# Staged version ─ add a new violation (NULL) to each file
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

ACTUAL_LINES=$(wc -l < "$BENCH_REPO/file1.php" | tr -d ' ')
echo "→ Actual line count per file (staged): $ACTUAL_LINES"

# ── 3. Run hyperfine ──────────────────────────────────────────────────────
FILES_STR="${FILES[*]}"
TRUNK_SHA="$(git -C "$TRUNK_WORKTREE" rev-parse --short HEAD)"

HF_ARGS=(--warmup "$WARMUP" --runs "$RUNS")

# Trunk baseline first so it appears at the top of the table.
TRUNK_CMD="php '$TRUNK_BIN' --git-staged --phpcs-path='$PHPCS' --standard=PSR2 --always-exit-zero $FILES_STR"
HF_ARGS+=(-n "trunk ($TRUNK_SHA)" "$TRUNK_CMD")

for idx in "${!CANDIDATE_PATHS[@]}"; do
  path="${CANDIDATE_PATHS[$idx]}"
  name="${CANDIDATE_NAMES[$idx]}"
  sha="$(git -C "$path" rev-parse --short HEAD)"
  bin="$path/bin/phpcs-changed"
  cmd="php '$bin' --git-staged --phpcs-path='$PHPCS' --standard=PSR2 --always-exit-zero $FILES_STR"
  HF_ARGS+=(-n "$name ($sha)" "$cmd")
done

echo ""
printf "Benchmark: %d staged PHP files, ~%d lines/file, --standard=PSR2\n" "$N_FILES" "$ACTUAL_LINES"
printf "  baseline: trunk (%s)\n" "$TRUNK_SHA"
for idx in "${!CANDIDATE_NAMES[@]}"; do
  path="${CANDIDATE_PATHS[$idx]}"
  printf "  candidate: %s (%s)\n" "${CANDIDATE_NAMES[$idx]}" "$(git -C "$path" rev-parse --short HEAD)"
done
echo ""

cd "$BENCH_REPO"
hyperfine "${HF_ARGS[@]}" --export-markdown "$RESULTS_FILE"

echo ""
echo "Results written to $RESULTS_FILE"
