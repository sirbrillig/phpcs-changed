# Benchmarking phpcs-changed

`benchmark.sh` measures the wall-clock time of running `phpcs-changed --git-staged`
against a set of changed PHP files, and compares the current branch to `trunk`.

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

| Branch | phpcs invocations per run |
|--------|--------------------------|
| trunk | up to `2 × N_FILES` — one per file per version (modified + unmodified) |
| feature branch | 1 — all file versions batched into a single phpcs call |

The dominant cost in each phpcs invocation is **process startup** (~250–400 ms
on typical hardware). The batch approach eliminates `2 × N_FILES − 1` of those
startups.

## Interpreting results

Hyperfine reports mean time, standard deviation, and a relative speedup ratio.
Example output for `N_FILES=10`:

```
Benchmark 1: batch (a75e2c2): 1 phpcs call
  Time (mean ± σ):      2.44 s ±  0.05 s
Benchmark 2: trunk (5c9f6b2): 20 phpcs calls
  Time (mean ± σ):      5.74 s ±  0.11 s

Summary: batch ran 2.36 ± 0.06 times faster than trunk
```

The speedup scales roughly linearly with `N_FILES`. A project with 20 changed
files should see approximately twice the speedup of a 10-file project.

The batch variant carries its own overhead (temp directory creation, one file
write per file version, one phpcs invocation on all files). This overhead
appears as a baseline cost that does not scale with `N_FILES`, so the
crossover point where batching wins is low — roughly 2 or more files.

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
