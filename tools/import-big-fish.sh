#!/usr/bin/env bash
set -euo pipefail

# Explicit import; never invoked automatically by Composer installation.
cd "$(dirname "${BASH_SOURCE[0]}")/.."
if [[ $# -gt 1 || ( $# -eq 1 && "$1" != "--download-only" ) ]]; then
    echo "Usage: tools/import-big-fish.sh [--download-only]" >&2
    exit 2
fi
mkdir -p data/scripts
curl --fail --location --retry 2 \
    https://fountain.io/_downloads/Big-Fish.fountain \
    --output data/scripts/Big-Fish.fountain.download
php -r '
$source = json_decode(file_get_contents("data/sources/big-fish.json"), true, 512, JSON_THROW_ON_ERROR);
$temp = $source["localPath"].".download";
if (!hash_equals($source["sha256"], hash_file("sha256", $temp))) {
    fwrite(STDERR, "Big Fish source changed; review the download before updating its manifest.\n");
    exit(1);
}
if (!rename($temp, $source["localPath"])) { exit(1); }
echo "Verified Big Fish download.\n";
'
if [[ "${1:-}" != "--download-only" ]]; then
    echo "Importing Big Fish; app:load replaces an existing script with the same ID."
    php bin/console app:load data/scripts/Big-Fish.fountain --no-interaction
fi
