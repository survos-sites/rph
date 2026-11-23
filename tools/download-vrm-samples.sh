#!/usr/bin/env bash
set -euo pipefail

# Sample VRM 1.0 characters for the 3D stage prototype (VRM Public License 1.0).
# Seed-san (c) VirtualCast, Inc.; VRM1_Constraint_Twist_Sample (c) 2022 pixiv Inc.
# These are model assets, not packages, so they are fetched explicitly and verified here.
cd "$(dirname "${BASH_SOURCE[0]}")/.."
base=https://raw.githubusercontent.com/vrm-c/vrm-specification/94e82dd346fa6cf0337c4421728640e5252dd38e/samples
mkdir -p public/media/vrm
while read -r sha path; do
    target=public/media/vrm/$(basename "$path")
    if [[ -f "$target" ]] && echo "$sha  $target" | shasum -a 256 -c --quiet; then continue; fi
    curl --fail --location --retry 2 "$base/$path" --output "$target.download"
    echo "$sha  $target.download" | shasum -a 256 -c --quiet || { echo "$path changed upstream; review before updating." >&2; exit 1; }
    mv "$target.download" "$target"
done <<'LIST'
624d0d554bc205bbdc33e22a68a2c3c20edebb3e573011ead8878a65e5329b23 Seed-san/vrm/Seed-san.vrm
12c2b97e95e700783a6a550dc0eee2d7880aeedccef9ae67bc4c5a2f0f2631a2 VRM1_Constraint_Twist_Sample/vrm/VRM1_Constraint_Twist_Sample.vrm
LIST
echo "VRM samples ready in public/media/vrm."
