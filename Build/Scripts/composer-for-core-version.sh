#!/usr/bin/env bash

# @todo This script is crap. It must fall and properly implemented in Build/Scripts/runTests.sh along with
#       adding two ddev core instances decoupled from the root composer.json.

# Abort on the first failing command. Without this a dependency resolution failure
# went unnoticed: the script still exited 0, and CI only failed later on a missing
# .Build/bin binary - which hid that the TYPO3 v13 install had never worked.
set -euo pipefail

composer_cleanup() {
    echo -e "💥 Cleanup folders"
    rm -Rf \
        .cache/phpstan/* \
        .Build/vendor/* \
        .Build/var/* \
        .Build/bin/* \
        .Build/Web/typo3conf/ext/* \
        .Build/Web/typo3/* \
        .Build/Web/typo3temp/* \
        composer.lock
}

# Install the dependency set for one TYPO3 major by temporarily requiring typo3/minimal.
# composer.json is restored afterwards - also if the install fails - so switching the
# core version never leaves the working tree modified.
composer_install_for_core() {
    local constraint="$1"

    cp composer.json composer.json.orig
    trap 'mv composer.json.orig composer.json' EXIT

    echo -e "💪 Enforce TYPO3 ${constraint}"
    php -dxdebug.mode=off "$(which composer)" require --no-update \
        "typo3/minimal":"${constraint}"
    echo -e "🔥 Update to selected dependencies"
    php -dxdebug.mode=off "$(which composer)" install
}

case "${1:-}" in
13)
    composer_cleanup
    composer_install_for_core "^13.4"
    ;;
14)
    composer_cleanup
    composer_install_for_core "^14.0"
    ;;
*)
    echo -e "🌀 Usage: composer-for-core-version.sh (13|14)" >&2
    exit 1
    ;;
esac
