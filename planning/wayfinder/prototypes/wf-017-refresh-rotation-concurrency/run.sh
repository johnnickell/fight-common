#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
DOCKER_BIN="${DOCKER_BIN:-docker}"
PHP_IMAGE="${FIGHT_COMMON_WF017_PHP_IMAGE:-fight-common-wf017-refresh-rotation}"

# shellcheck source=../../../../bin/.disposable-database-runtime
source "${ROOT_DIR}/bin/.disposable-database-runtime"
fight_common_install_disposable_database_cleanup

"${DOCKER_BIN}" build \
    --tag "${PHP_IMAGE}" \
    --file "${ROOT_DIR}/planning/wayfinder/prototypes/wf-017-migration-uniqueness/Dockerfile" \
    "${ROOT_DIR}"

install_dependencies() {
    local directory="$1"
    "${DOCKER_BIN}" container run --rm \
        -v "${ROOT_DIR}:/workspace" \
        -w "/workspace/${directory}" \
        --env COMPOSER_HOME=/tmp/wf017-composer \
        "${PHP_IMAGE}" \
        composer install --no-interaction --no-progress
}

install_dependencies planning/wayfinder/prototypes/wf-017-transaction-seam/doctrine
install_dependencies planning/wayfinder/prototypes/wf-017-transaction-seam/laravel
install_dependencies planning/wayfinder/prototypes/wf-017-transaction-seam/codeigniter
install_dependencies planning/wayfinder/prototypes/wf-017-migration-uniqueness/yii

fight_common_start_disposable_databases
mkdir -p "${ROOT_DIR}/planning/wayfinder/prototypes/wf-017-refresh-rotation-concurrency/receipts"

run_lane() {
    local framework="$1"
    local database="$2"
    local url="$3"
    local receipt_name
    receipt_name="$(printf '%s-%s' "${framework}" "${database}" | tr '[:upper:]' '[:lower:]')"

    "${DOCKER_BIN}" container run --rm \
        --network "${FIGHT_COMMON_DATABASE_NETWORK}" \
        -v "${ROOT_DIR}:/workspace" \
        -w /workspace/planning/wayfinder/prototypes/wf-017-refresh-rotation-concurrency \
        --env "PROTOTYPE_FRAMEWORK=${framework}" \
        --env "PROTOTYPE_DATABASE_URL=${url}" \
        --env "PROTOTYPE_RECEIPT=/workspace/planning/wayfinder/prototypes/wf-017-refresh-rotation-concurrency/receipts/${receipt_name}.json" \
        "${PHP_IMAGE}" \
        php run.php
}

for database in mysql postgresql; do
    if [[ "${database}" == mysql ]]; then
        url="${FIGHT_COMMON_MYSQL_DSN}"
    else
        url="${FIGHT_COMMON_POSTGRESQL_DSN}"
    fi

    run_lane Symfony "${database}" "${url}"
    run_lane Slim "${database}" "${url}"
    run_lane Laravel "${database}" "${url}"
    run_lane Yii "${database}" "${url}"
    run_lane CodeIgniter "${database}" "${url}"
done
