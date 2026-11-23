#!/usr/bin/env bash
# Download the local image model (resumable), then generate a script's pictures.
# Safe to stop and rerun at any point: Hugging Face resumes partial downloads from its cache,
# and app:pictures skips pictures that already exist.
#
#   tools/generate-pictures.sh christmas-facing-west
set -uo pipefail
script="${1:-christmas-facing-west}"
repo="Tongyi-MAI/Z-Image-Turbo"
cd "$(dirname "$0")/.."

attempt=0
until hf download "$repo" --quiet >/dev/null; do
  attempt=$((attempt + 1))
  echo "$(date '+%H:%M:%S') download interrupted (attempt $attempt); retrying in 30s" >&2
  sleep 30
done
echo "$(date '+%H:%M:%S') model ready: $repo"

until bin/console app:pictures "$script"; do
  echo "$(date '+%H:%M:%S') generation stopped; retrying in 30s" >&2
  sleep 30
done
bin/console app:timeline "$script"
