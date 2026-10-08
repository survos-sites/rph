# Import inputs, deployment data, and generated media

## Current storage

Fountain import inputs are in local `data/scripts/`, ignored by Git. The recovered Clementine, Bob/Sandy, interview and joke files live there. Original recovered sources and the Oz Celtx archive are under ignored `var/script-recovery/`; extracted Celtx assets live under ignored `var/celtx/`. These directories do not arrive on the live server from a Git deployment.

The repository does contain Oz cast/direction/shot JSON, SVG character portraits, and the small prototype JPEG shot cache under `public/media/shots/oz/`. Generated voices under `public/media/voices/` are ignored. Therefore the repo currently mixes small demo illustrations with local-only inputs/audio; it is not a complete production dataset or media backup.

## Big Fish

The official [Fountain homepage](https://fountain.io/) provides [Big Fish as a Fountain sample](https://fountain.io/_downloads/Big-Fish.fountain). Its title page credits John August and identifies Columbia Pictures copyright. The original is stored locally at `data/scripts/Big-Fish.fountain`, not vendored into Git. The committed `data/sources/big-fish.json` records URL, provenance and SHA-256 so the same input can be retrieved and checked.

```bash
tools/import-big-fish.sh
# Download and verify without touching the database:
tools/import-big-fish.sh --download-only
```

The script creates the input directory, downloads with curl, verifies the committed SHA-256, and invokes `app:load`. It is an explicit operation, not a Composer install hook. Reimporting replaces the existing Big Fish record; do not use it blindly over edited production data. The current parser imports 198 scene records (including its opening segment) and 53 character records. These are parser output counts, not an independently verified authoritative breakdown. Big Fish exercises more Fountain features than our initial fixtures; this import does not establish full format conformance. Its multiline title-page notes are now handled correctly.

## Seeding recommendation

Do **not** attach database seeding to `composer install`. Dependency installation can happen during builds, without database access, and on repeated deployments. Our existing `app:load` also removes/recreates an existing script with the same filename-derived ID. It must not be silently rerun over edited production scripts.

Use an explicit deployment/bootstrap step after migrations instead. Proposed next implementation: a versioned seed manifest plus `app:seed` with skip-existing as the default, an explicit refresh option, source hash verification, and a report of missing inputs. The manifest should reference bundled redistributable fixtures or private persistent source storage; external samples can be fetched deliberately. Composer can expose a named `seed` shortcut if useful, but it should be manually invoked and not an install hook. This command has not been implemented yet.

Text-only initial data can be seeded independently of rendering or audio. We should decide which scripts belong on the public live site before promoting the entire local collection. The official availability of a sample is not a statement that it belongs in every public deployment.

## Media recommendation

Start with text, cast and production metadata on the live site. Use existing portraits or a clear text-only frame when a shot lacks a render. Audio should be optional and a missing clip should not break a scene. Avoid generating an entire screenplay's images/audio as part of a deploy.

Keep source inputs, derived media, and the database separate:

- **Source inputs:** private persistent storage, source URL/license/provenance and content hash in a manifest.
- **Database:** canonical script/shot/cast records and media references, backed up independently.
- **Generated images/audio:** persistent media storage with content-hash keys; begin with a persistent server volume, introduce S3-compatible storage when worker/public delivery requirements justify it.

Keep Symfony and provider credentials on the server. A Unity worker receives a versioned scene/shot job and returns a media result; a speech worker receives an explicit text/voice request. Cache keys must include relevant source content, character recipe/assets, directing/camera configuration and renderer version; audio keys include provider/model/voice/text/delivery parameters. Record ready/pending/failed state and duration/dimensions. Publish only completed assets and serve through stable application/storage URLs.

For the first live milestone, use a small curated script set and a few prepared images/clips. Defer bulk regeneration, storage-provider selection and automatic seed integration until that set and storage policy are agreed. The macOS `say` baseline is not available on a typical Linux production server; pre-generated audio or another backend is required there.

No live database or deployment was changed by this addition. Big Fish was imported into the local database only.

## Where the earlier examples came from

The five earlier examples were recovered from the old [survos/rph](https://github.com/survos/rph/tree/7780d8ab5037293d820da0fd446b22b7212a3f65) at commit `7780d8ab5037293d820da0fd446b22b7212a3f65`. They were selected to recover the examples requested during the revival, not as a polished public demonstration set:

| Imported example | Original location |
| --- | --- |
| Talented Clementine | `data/paul/talented-clementine.fountain` (an FDX copy also survived) |
| Left-handed joke | `data/tac/lefthanded.fount` |
| Politician joke | `data/tac/politician.fount` |
| Job interview | `data/job-interview.font` |
| Bob and Sandy | A `defaultContent` heredoc inside `src/Controller/ScriptController.php`, rather than a standalone authored script file |

They were normalized into Fountain for the current importer. Local recovery manifests record original blobs and changes. The Wizard of Oz came separately from the desktop Celtx sample in `n8willis/celtx`; it is a small tutorial adaptation with production assets. Big Fish is the official Fountain sample and is a stronger full-screenplay parser exercise. Keep the old snippets as optional regression/recovery inputs rather than assuming they should populate the public demo site.

## Oz and other source collections

Oz already exists in the local RPH database. To reproduce its import elsewhere, run `tools/import-oz.sh` after database migrations. It downloads the pinned desktop Celtx sample, checks SHA-256, and invokes `app:load`. `--download-only` avoids touching the database. The committed manifest is `data/sources/the-wizard-of-oz.json`; the input goes into ignored `data/scripts/the-wizard-of-oz.celtx`. This restores the four-scene screenplay plus embedded production metadata/assets. It does not generate voice clips or new images, and reimport replaces an existing script of the same ID.

[IMSDb](https://imsdb.com/) supplies screenplay pages in HTML, not native Fountain. A converter would need to isolate the screenplay, preserve whitespace/context, distinguish dialogue from action, strip page furniture, and review output against the original. Its [disclaimer](https://imsdb.com/disclaimer.html) does not grant a general reuse license. Defer bulk conversion: more unreviewed scripts do not supply the shot annotations we need for Cine-AI. If a particular authorized script becomes useful, convert it as a private source input with provenance and regression fixtures.

For a publicly reusable Oz demonstration, [Baum's original novel](https://www.gutenberg.org/ebooks/55) is listed by Project Gutenberg as public domain in the USA. A short adaptation authored from that text could supply a better curated demo scene and explicit directing annotations. This status concerns the novel, not the 1939 movie screenplay, its performances/designs, or every asset packaged in the Celtx sample. No new adaptation or IMSDb converter has been implemented.

## Existing public-domain and short-form tooling inventory

Local code inventory, 8 October 2026. A Gutenberg/Gutendex/RDF catalog client was not found in the searched Symfony repos, Survos/package locations, or restored archives. This is a bounded search result, not proof that no historical copy exists elsewhere.

The closest modern implementation is **Bard**, using Open Source Shakespeare rather than Gutenberg:

- `~/sites/bard/src/Service/OssArchive.php`: `app:download`, downloads/validates `oss-textdb.zip` using the shared FetchBundle downloader and reads four corpus tables directly via ZIP streams.
- `~/sites/bard/src/Service/AppService.php`: `app:load`, persists works, chapters/scenes, characters and paragraphs, with batched loading and explicit force/refresh options.
- `~/sites/bard/src/Service/FountainFormatter.php`: exports structured scenes to Fountain, including speakers and stage directions.
- `~/sites/bard/src/Service/VectorService.php`: scene-level document formatting and vector indexing with structured metadata; useful later for finding small exchanges, not required for initial imports.

A legacy **knock-knock joke application** survives at `~/Documents/Codex/2026-10-02/a-long-time-ago-i-bought/work/smokescreen/tobacco/tffa/voxeo/knock/`. Its `knock.txt` stores setup/punchline pairs separated by a colon; `knock.php` chooses a pair and runs a VoiceXML conversation. Text-to-speech and prerecorded-audio variants exist. This is historical code/data, not a modern integrated service or an established public-domain source.

`~/sandesa/scenea/text_to_tei.php` also contains an old screenplay-text to TEI converter: paragraph/indentation heuristics identify scenes, characters and dialogue. It is not a Gutenberg client, and should be treated as reference logic rather than a ready current importer.

For new short-form exploration, inspect [Merry's Book of Puzzles, Gutenberg 53847](https://www.gutenberg.org/ebooks/53847) and [English Jests and Anecdotes, Gutenberg 49370](https://www.gutenberg.org/ebooks/49370). Both catalog pages list public-domain status in the USA. Review individual items for suitability: these are historic collections, not automatically good contemporary demo material.

Recommended first slice: select a handful of suitable question/answer or setup/punchline items, retain ebook/item identifiers and untouched original text, then separately author two-character Fountain scenes and shot timing. Speaker assignments, gestures, pauses and reaction shots are our adaptation. RPH already imports the resulting Fountain; Bard supplies reusable structured-export patterns. A Gutenberg downloader/item extractor, reviewed selection workflow and new imported joke/riddle corpus have not yet been implemented.
