#!/usr/bin/env bash
# Auto-bump the plugin patch version before every `git commit`.
# Runs as a PreToolUse hook on Bash tool calls.
# The bumped file is staged so the version change is part of the same commit.

set -euo pipefail

# ── Read the Bash command from Claude's hook JSON (stdin) ────────────────────
CMD=$(jq -r '.tool_input.command // ""')

# Only act on git commit commands; skip --amend (would double-bump) and --dry-run.
echo "$CMD" | grep -qE 'git\s+commit'   || exit 0
echo "$CMD" | grep -q '\-\-amend'       && exit 0
echo "$CMD" | grep -q '\-\-dry-run'     && exit 0

# ── Locate the plugin's main PHP file ────────────────────────────────────────
PHPFILE="freescout-woocommerce-myaccount.php"

if [ ! -f "$PHPFILE" ]; then
    echo "bump-version: $PHPFILE not found – skipping" >&2
    exit 0
fi

# ── Extract the current version from the plugin header ───────────────────────
CURRENT=$(grep -oP '(?<=\* Version:\s{5})[\d.]+' "$PHPFILE" || true)

if [ -z "$CURRENT" ]; then
    echo "bump-version: could not parse version from $PHPFILE – skipping" >&2
    exit 0
fi

# ── Compute the next patch version ───────────────────────────────────────────
IFS='.' read -r major minor patch <<< "$CURRENT"
patch=$(( patch + 1 ))
NEW="${major}.${minor}.${patch}"

# ── Apply the bump ────────────────────────────────────────────────────────────
sed -i "s/ \* Version:     ${CURRENT}/ \* Version:     ${NEW}/" "$PHPFILE"
sed -i "s/define( 'FSWA_VERSION', '${CURRENT}' )/define( 'FSWA_VERSION', '${NEW}' )/" "$PHPFILE"

# ── Stage the file so the bump rides in the same commit ──────────────────────
git add "$PHPFILE"

echo "bump-version: ${CURRENT} → ${NEW}"
