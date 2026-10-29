#!/usr/bin/env bash
# Fetch live App Platform spec, reconcile DOCR image + digest, update app, wait for ACTIVE.
set -euo pipefail

: "${IMAGE_REPOSITORY:?IMAGE_REPOSITORY is required}"
: "${IMAGE_DIGEST:?IMAGE_DIGEST is required}"
: "${COMPONENTS:?COMPONENTS is required (comma-separated)}"

if [[ -z "${APP_ID:-}" && -z "${APP_NAME:-}" ]]; then
  echo "APP_ID or APP_NAME is required" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKDIR="${RUNNER_TEMP:-/tmp}/app-platform-deploy-$$"
mkdir -p "$WORKDIR"
trap 'rm -rf "$WORKDIR"' EXIT

LIVE_SPEC="$WORKDIR/live.yaml"
PATCHED_SPEC="$WORKDIR/patched.yaml"

if [[ -n "${APP_NAME:-}" ]]; then
  export APP_NAME
fi

if [[ -z "${APP_ID:-}" ]]; then
  echo "Resolving app id for name=${APP_NAME}"
  APP_ID="$(doctl apps list -o json | python3 -c '
import json, os, sys
name = os.environ["APP_NAME"]
apps = json.load(sys.stdin)
matches = [a for a in apps if a.get("spec", {}).get("name") == name]
if len(matches) != 1:
    raise SystemExit(f"expected 1 app named {name!r}, found {len(matches)}")
print(matches[0]["id"])
')"
fi
export APP_ID
echo "Using APP_ID=${APP_ID}"

echo "Fetching live spec"
doctl apps spec get "$APP_ID" >"$LIVE_SPEC"

PATCH_ARGS=(
  python3 "$SCRIPT_DIR/reconcile-app-platform.py"
  --live "$LIVE_SPEC"
  --out "$PATCHED_SPEC"
  --repository "$IMAGE_REPOSITORY"
  --digest "$IMAGE_DIGEST"
  --components "$COMPONENTS"
)
if [[ -n "${CONTRACT_SPEC:-}" ]]; then
  PATCH_ARGS+=(--contract "$CONTRACT_SPEC")
fi
"${PATCH_ARGS[@]}"

echo "Updating app"
doctl apps update "$APP_ID" --spec "$PATCHED_SPEC"

echo "Waiting for deployment to become ACTIVE"
ATTEMPTS="${DEPLOY_WAIT_ATTEMPTS:-60}"
SLEEP_SECONDS="${DEPLOY_WAIT_SLEEP_SECONDS:-10}"
i=1
while [[ "$i" -le "$ATTEMPTS" ]]; do
  phase="$(doctl apps list-deployments "$APP_ID" -o json | python3 -c '
import json, sys
deps = json.load(sys.stdin)
if not deps:
    print("UNKNOWN")
else:
    # newest first
    deps = sorted(deps, key=lambda d: d.get("created_at") or "", reverse=True)
    print(deps[0].get("phase") or "UNKNOWN")
')"
  echo "Attempt ${i}/${ATTEMPTS}: phase=${phase}"
  case "$phase" in
    ACTIVE)
      echo "Deployment ACTIVE"
      exit 0
      ;;
    ERROR|CANCELED|SUPERSEDED)
      echo "Deployment failed with phase=${phase}" >&2
      exit 1
      ;;
  esac
  sleep "$SLEEP_SECONDS"
  i=$((i + 1))
done

echo "Timed out waiting for ACTIVE deployment" >&2
exit 1
