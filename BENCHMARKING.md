# Benchmarking phpcs-changed

`benchmark.sh` measures the wall-clock time of running `phpcs-changed --git-staged`
against a set of changed PHP files, comparing the **current branch** to `trunk`.

It uses [hyperfine](https://github.com/sharkdp/hyperfine) for statistical
measurement and a self-contained throwaway git repo for reproducible test inputs.

## Prerequisites

```bash
brew install hyperfine   # macOS
composer install         # ensure vendor/bin/phpcs is present
```

## Running the benchmark

```bash
./benchmark.sh
```

By default this benchmarks **10 staged PHP files**, **10 measurement runs**,
and **2 warmup runs**. All three are tunable via environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `N_FILES` | `10` | Number of changed PHP files in the test scenario |
| `RUNS` | `10` | Hyperfine measurement runs per command |
| `WARMUP` | `2` | Hyperfine warmup runs (excluded from results) |

Examples:

```bash
# Quicker smoke test
N_FILES=5 RUNS=3 WARMUP=1 ./benchmark.sh

# Higher-confidence results with more files
N_FILES=20 RUNS=20 ./benchmark.sh
```

Results are written to `benchmark-results.md` (Markdown table, suitable for
pasting into a PR description).

## How it works

### Test environment

The script creates a temporary git repo containing `N_FILES` PHP files.
Each file has two committed versions:

- **HEAD (unmodified)** — contains `TRUE` and `FALSE` uppercase constants,
  which are PSR2 violations. Having violations in the committed version
  ensures the "scan unmodified file" code path is exercised on every file.
- **Staged (modified)** — the HEAD content plus a new function using `NULL`,
  adding a third PSR2 violation.

Because both versions have violations, every benchmarked call must do a full
scan of both the modified and unmodified file for every file in the list.
This is the worst case for the per-file approach and the fairest comparison.

### Trunk worktree

The script uses `git worktree add` to check out `trunk` as a sibling directory
(`.bench-trunk/`) so both branches can be invoked without switching branches.
The worktree is created on the first run and reused on subsequent runs.

### What is being compared

The script runs the same `phpcs-changed --git-staged` command against the same
set of test files using two builds: the current branch and `trunk`. Hyperfine
runs both commands back-to-back the same number of times, so any difference in
wall-clock time reflects a real performance difference between the two builds.

## Interpreting results

Hyperfine reports mean time, standard deviation, and a relative speedup ratio.
Example output for `N_FILES=10`:

```
Benchmark 1: my-feature-branch (a75e2c2)
  Time (mean ± σ):      2.44 s ±  0.05 s
Benchmark 2: trunk (5c9f6b2)
  Time (mean ± σ):      5.74 s ±  0.11 s

Summary: my-feature-branch ran 2.36 ± 0.06 times faster than trunk
```

## Artifacts

The following files are created outside the git index and are gitignored:

| Path | Description |
|------|-------------|
| `.bench-trunk/` | `git worktree` checkout of `trunk` |
| `benchmark-results.md` | Hyperfine markdown export from the last run |

To remove the worktree when you no longer need it:

```bash
git worktree remove .bench-trunk
```
