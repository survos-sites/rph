#!/usr/bin/env python3
"""Fetch pinned research annotation inputs; no database or film media changes."""
import hashlib
import json
from pathlib import Path
from urllib.request import urlopen

root = Path(__file__).resolve().parent.parent
manifest = json.loads((root / 'data/sources/psl-annotations.json').read_text())
for source in manifest['items']:
    with urlopen(source['sourceUrl'], timeout=30) as response:
        body = response.read()
    if hashlib.sha256(body).hexdigest() != source['sha256']:
        raise SystemExit(f"Source changed: {source['id']}; review before updating manifest")
    destination = root / source['localPath']
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_suffix('.download')
    temporary.write_bytes(body)
    temporary.replace(destination)
    print(f"Verified {source['id']}: {source['cueCount']} timed annotation cues")
