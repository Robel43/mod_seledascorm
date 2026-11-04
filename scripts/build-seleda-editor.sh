#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SELEDA_REPO_URL=${SELEDA_STUDIO_REPO_URL:-https://github.com/Robel43/Timhirt-Studio.git}

# Timhirt-Studio may be private. CI can provide a fine-grained token with
# read-only Contents access through SELEDA_STUDIO_TOKEN. Keep the public URL
# unchanged for local environments that already have Git credentials.
if [ -n "${SELEDA_STUDIO_TOKEN:-}" ]; then
    SELEDA_REPO_URL="https://x-access-token:${SELEDA_STUDIO_TOKEN}@github.com/Robel43/Timhirt-Studio.git"
fi

if [ -n "${SELEDA_STUDIO_REF:-}" ]; then
    SELEDA_REF="$SELEDA_STUDIO_REF"
elif [ -f "$ROOT_DIR/.seleda-editor-ref" ]; then
    SELEDA_REF=$(cat "$ROOT_DIR/.seleda-editor-ref")
else
    SELEDA_REF="Develop"
fi

BUILD_VERSION=${APP_VERSION:-$SELEDA_REF}
WORK_DIR=$(mktemp -d)
IMAGE_TAG="seleda-embedded-editor:plugin-build"
CONTAINER_ID=""

cleanup() {
    if [ -n "$CONTAINER_ID" ]; then
        docker rm -f "$CONTAINER_ID" >/dev/null 2>&1 || true
    fi
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT INT TERM

echo "Building embedded Seleda from $SELEDA_REPO_URL @ $SELEDA_REF"

git -C "$WORK_DIR" init -q
git -C "$WORK_DIR" remote add origin "$SELEDA_REPO_URL"
git -C "$WORK_DIR" fetch --depth 1 origin "$SELEDA_REF"
git -C "$WORK_DIR" checkout -q FETCH_HEAD

test -f "$WORK_DIR/Dockerfile.embedded"

docker build     -f "$WORK_DIR/Dockerfile.embedded"     --build-arg SELEDA_EMBEDDED_VERSION="$BUILD_VERSION"     -t "$IMAGE_TAG"     "$WORK_DIR"

rm -rf "$ROOT_DIR/dist/static"
mkdir -p "$ROOT_DIR/dist/static"

CONTAINER_ID=$(docker create "$IMAGE_TAG")
docker cp "$CONTAINER_ID:/static/." "$ROOT_DIR/dist/static/"
docker rm "$CONTAINER_ID" >/dev/null
CONTAINER_ID=""

test -f "$ROOT_DIR/dist/static/index.html"
test -d "$ROOT_DIR/dist/static/app"
test -d "$ROOT_DIR/dist/static/files"
test -d "$ROOT_DIR/dist/static/libs"

# Verify the Seleda-specific V1 Content Blocks and modified editors are present.
for path in \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/mcq-feedback" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/speaking-practice" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/page-navigation" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/dragdrop" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/beforeafter" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/map" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/quick-questions" \
    "$ROOT_DIR/dist/static/files/perm/idevices/base/complete"
do
    if [ ! -d "$path" ]; then
        echo "Missing required Seleda Content Block/editor customization: $path" >&2
        exit 1
    fi
done

if ! grep -RIl -m1 "Seleda Studio" "$ROOT_DIR/dist/static" >/dev/null 2>&1; then
    echo "Embedded bundle does not contain expected Seleda Studio branding." >&2
    exit 1
fi

echo "Embedded Seleda bundle written to $ROOT_DIR/dist/static"
