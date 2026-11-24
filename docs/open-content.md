# Reusable source scripts and comic samples

Run from the RPH root using the Symfony CLI:

```sh
php bin/console app:download-open-content --list
php bin/console app:download-open-content
# Small starting set for scene experiments:
php bin/console app:download-open-content --only=mommy_monster --only=perpetual
# Preview or choose a different local storage directory:
php bin/console app:download-open-content --dry-run
php bin/console app:download-open-content --output /path/to/source-library
```

The default downloads all nine available works: six David Bragg short screenplays
(original Fountain and PDF, plus author notes and license declaration), Alex
Bryant's Treasure Island PDF, xkcd #6 (image, transcript/metadata, license page),
and fkfd #139 (image and source/license page). These are curated samples, not
complete comic archives. The PDF embeds its license; no OCR is needed to download it.

Original files go under ignored `data/scripts/open-content/<id>/`. Each complete
work gets `provenance.json` containing attribution, license, source URLs, SHA-256
hashes and verification time. The committed `data/sources/open-content.json`
pins Bragg to a Git commit and all files to reviewed hashes. Unchanged local
files are verified and skipped. Changed local files are never overwritten;
changed remote files fail verification before being installed. Failures are
reported per work with a nonzero exit code; rerunning resumes verified downloads.
Dynamic source/license pages may change: review changes before updating hashes.

Start by reading `mommy_monster/mommy_monster.fountain` (3 pages) and
`perpetual/perpetual.fountain` (5 pages), then select a few scenes for direction.
For an explicit local database import after review:

```sh
php bin/console app:load data/scripts/open-content/mommy_monster/mommy_monster.fountain
```

Importing replaces an existing script with the same filename-derived ID. The
downloader itself does not import, extract scenes, create videos or call M6.
Keep adapted scenes separate from downloaded originals and carry attribution
into future scene/movie metadata and credits.

Bragg's works are CC BY 4.0 and explicitly permit filming and commercial
adaptation. Treasure Island is CC BY-SA 4.0 (share-alike); xkcd is CC BY-NC 2.5
and fkfd is CC BY-NC 4.0 (noncommercial). License IDs are retained per work;
downloading does not make the whole collection uniformly licensed.

Two earlier leads are recorded as deferred in the manifest: Pepper&Carrot's
current page combines CC BY 4.0 with an AI-derivation prohibition, which needs
review for the planned workflow; Adam Oster offers CC BY-SA plays but requires
contact for complete scripts. Neither has an automatic download in this seed.

The command uses FetchBundle's ChunkDownloader and ZipArchive. Bragg files
are read from one commit-pinned GitHub ZIP per invocation, extracting only
manifest-selected members and verifying each member hash. Other sources use
direct downloads. FetchBundle handles HTTP, retry and resumable `.part` files;
its download cache lives in `var/open-content/downloads/`, keyed by URL hash.
No GitHub token is required. If upstream content changes, review it and remove
the affected cached download before deliberately updating the pinned hash.

## Imported scene previews

All six Bragg screenplays are imported locally (61 scenes). Their original
Fountain text is unchanged. The library and script overview now link directly
to one illustrated scene per screenplay:

| Script | Scene | Reference |
| --- | --- | --- |
| bad-kitty | 5 | Lora's video call in the spare bedroom |
| mommy-monster | 4 | Evie and her mother in the bedroom |
| no-overnight-parking | 1 | Leon arriving at the garage |
| perpetual | 2 | Fran in the office kitchen |
| tabula-rasa | 8 | Wash examining his annotated pages |
| thorium-blue | 4 | Rooftop conversation during the blackout |

Open `/` and choose **Illustrated scene**. **Slideshow** advances through the
original script blocks; enable **Local voice** to hear dialogue. Each preview
uses one generated scene reference held across the scene, not a finished film
or a separate image for every camera angle. The remaining scenes have their
original text and cached dialogue but no new artwork yet.

`data/cast/<id>.json` defines the cast and pins `engine: say` for these six
productions. This avoids depending on the unavailable local/M6 VoiceStudio
endpoint without changing global voice settings or other productions. Every
dialogue block in these scripts has a cached MP3 (173 blocks; repeated lines
reuse files). To switch later, change the cast engine to `voicestudio`, supply
the desired voice profile, and regenerate with `app:voices`.

`data/shots/<id>.json` maps the illustrated scene's element IDs to its image and
records the source/license credit. `data/media/<id>.json` records the image's
SHA-256 and generated origin. Artwork lives at
`public/media/generated/<id>/scene-reference.png`; speech is under
`public/media/voices/`. Both are ignored runtime assets, so copy these folders
when moving the demo to M6 or another server. Manifest files alone do not include
the images. The play PDF and comics remain downloaded source references, not
converted Fountain productions.

Reproduce the imports explicitly (replaces these script IDs in the database):

```sh
php bin/console app:download-open-content
php bin/console app:load data/scripts/open-content
for script in bad-kitty mommy-monster no-overnight-parking perpetual tabula-rasa thorium-blue; do
    php bin/console app:voices "$script"
done
```

No M6 deployment or movie rendering is performed by these commands.
