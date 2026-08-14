#!/bin/sh

set -eu

prototype_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

docker run --rm \
    --volume "$prototype_dir:/prototype" \
    --workdir /prototype \
    node:22.22.0-bookworm-slim \
    sh -lc 'npm ci --ignore-scripts && npm run prototype'
