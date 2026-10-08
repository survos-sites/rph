#!/usr/bin/env bash
set -euo pipefail

# Explicit import; never invoked automatically by Composer installation.
cd "$(dirname "${BASH_SOURCE[0]}")/.."
if [[ $# -gt 1 || ( $# -eq 1 && "$1" != "--download-only" ) ]]; then
    echo "Usage: tools/import-oz.sh [--download-only]" >&2
    exit 2
fi
mkdir -p data/scripts
curl --fail --location --retry 2 \
    https://raw.githubusercontent.com/n8willis/celtx/755bb095d82817222fbf64fe4c8b6669bfe5beff/mozilla/celtx/app/profile/CeltxSamples/1_TheWizard.celtx \
    --output data/scripts/the-wizard-of-oz.celtx.download
php -r '
$source = json_decode(file_get_contents("data/sources/the-wizard-of-oz.json"), true, 512, JSON_THROW_ON_ERROR);
$temp = $source["localPath"].".download";
if (!hash_equals($source["sha256"], hash_file("sha256", $temp))) {
    fwrite(STDERR, "Oz source changed; review the download before updating its manifest.\n");
    exit(1);
}
if (!rename($temp, $source["localPath"])) { exit(1); }
echo "Verified Oz download.\n";
'
if [[ "${1:-}" != "--download-only" ]]; then
    echo "Importing Oz; app:load replaces an existing script with the same ID."
    php bin/console app:load data/scripts/the-wizard-of-oz.celtx --no-interaction
fi
