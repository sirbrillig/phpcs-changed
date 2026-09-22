#!/usr/bin/env bash
# release.sh — tag and publish a new phpcs-changed release on GitHub
#
# Usage:
#   ./release.sh <version>            eg: ./release.sh 3.1.0
#   ./release.sh --dry-run <version>  run all checks but do not create the release
#
# Before running this, merge a PR that sets getVersion() in
# PhpcsChanged/functions.php to <version>. The script refuses to release unless:
#   - you are on trunk with no uncommitted changes to tracked files
#   - local trunk matches origin/trunk, so the release commit is on the remote
#   - getVersion() returns exactly <version>
#   - the tag v<version> does not already exist
#   - <version> is higher than the latest existing release tag
#
# It then creates the v<version> tag and GitHub release (with generated notes)
# at the current origin/trunk commit using the gh CLI.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

fail() {
	echo "Error: $*" >&2
	exit 1
}

DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
	DRY_RUN=true
	shift
fi

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	fail "Usage: $0 [--dry-run] <version> (eg: $0 3.1.0)"
fi
VERSION="${VERSION#v}"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	fail "Version '$VERSION' must look like X.Y.Z"
fi
TAG="v$VERSION"

command -v gh >/dev/null || fail "The gh CLI is required: https://cli.github.com/"
command -v php >/dev/null || fail "php is required to read the version from PhpcsChanged/functions.php"

BRANCH="$(git branch --show-current)"
[[ "$BRANCH" == "trunk" ]] || fail "You must be on trunk to release (currently on '$BRANCH')"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
	fail "You have uncommitted changes; commit or stash them first"
fi

echo "Fetching origin..."
git fetch --quiet --tags origin trunk

LOCAL_SHA="$(git rev-parse HEAD)"
REMOTE_SHA="$(git rev-parse origin/trunk)"
if [[ "$LOCAL_SHA" != "$REMOTE_SHA" ]]; then
	fail "Local trunk ($(git rev-parse --short HEAD)) does not match origin/trunk ($(git rev-parse --short origin/trunk)). Merge the version bump via a PR and pull before releasing."
fi

CODE_VERSION="$(php -r 'require "PhpcsChanged/functions.php"; echo PhpcsChanged\getVersion();')"
if [[ "$CODE_VERSION" != "$VERSION" ]]; then
	fail "getVersion() in PhpcsChanged/functions.php returns '$CODE_VERSION', not '$VERSION'. Merge a PR bumping the version first."
fi

if git rev-parse --quiet --verify "refs/tags/$TAG" >/dev/null; then
	fail "Tag $TAG already exists"
fi

LATEST_TAG="$(git tag --list 'v*' --sort=-v:refname | head -n 1)"
if [[ -n "$LATEST_TAG" ]]; then
	if ! php -r 'exit(version_compare($argv[1], $argv[2], ">") ? 0 : 1);' "$VERSION" "${LATEST_TAG#v}"; then
		fail "Version $VERSION is not higher than the latest release tag $LATEST_TAG"
	fi
fi

echo "All checks passed: releasing $TAG at $(git rev-parse --short "$REMOTE_SHA") (previous release: ${LATEST_TAG:-none})"

if [[ "$DRY_RUN" == "true" ]]; then
	echo "Dry run; not creating the release."
	exit 0
fi

read -r -p "Create tag and GitHub release $TAG? [y/N] " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
	echo "Aborted."
	exit 1
fi

gh release create "$TAG" --target "$REMOTE_SHA" --title "$TAG" --generate-notes
git fetch --quiet --tags origin
echo "Released $TAG."
