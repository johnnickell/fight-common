#!/usr/bin/env bash

set -euo pipefail

prototype_dir="$(cd "$(dirname "$0")" && pwd)"
container_name="fight-wf017-mercure-version"
secret="wf017-prototype-secret-key-32bytes"

cleanup() {
    docker rm --force "$container_name" >/dev/null 2>&1 || true
}

run_lane() {
    local version="$1"
    local protocol="$2"
    local image="dunglas/mercure:${version}"

    cleanup
    docker run --rm --detach \
        --name "$container_name" \
        --env SERVER_NAME=:80 \
        --env MERCURE_PUBLISHER_JWT_KEY="$secret" \
        --env MERCURE_SUBSCRIBER_JWT_KEY="$secret" \
        --env MERCURE_EXTRA_DIRECTIVES=anonymous \
        --publish 127.0.0.1::80 \
        "$image" >/dev/null

    local published_port
    published_port="$(docker port "$container_name" 80/tcp)"
    published_port="${published_port##*:}"
    local hub_url="http://127.0.0.1:${published_port}/.well-known/mercure"

    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if curl --fail --silent --show-error "http://127.0.0.1:${published_port}/" >/dev/null; then
            break
        fi

        if [[ "$attempt" == 10 ]]; then
            docker logs "$container_name"
            exit 1
        fi

        sleep 0.25
    done

    local image_digest
    image_digest="$(docker image inspect "$image" --format '{{index .RepoDigests 0}}')"
    php "$prototype_dir/probe.php" "$hub_url" "$secret" "$image_digest" "$version" "$protocol"
    cleanup
}

trap cleanup EXIT

run_lane v0.24.2 legacy
run_lane v1.0.0-alpha.3 modern
