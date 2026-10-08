# Import inputs, deployment data, and generated media

## Current storage

Fountain import inputs are in local `data/scripts/`, ignored by Git. The recovered Clementine, Bob/Sandy, interview and joke files live there. Original recovered sources and the Oz Celtx archive are under ignored `var/script-recovery/`; extracted Celtx assets live under ignored `var/celtx/`. These directories do not arrive on the live server from a Git deployment.

The repository does contain Oz cast/direction/shot JSON, SVG character portraits, and the small prototype JPEG shot cache under `public/media/shots/oz/`. Generated voices under `public/media/voices/` are ignored. Therefore the repo currently mixes small demo illustrations with local-only inputs/audio; it is not a complete production dataset or media backup.

## Big Fish

The official [Fountain homepage](https://fountain.io/) provides [Big Fish as a Fountain sample](https://fountain.io/_downloads/Big-Fish.fountain). Its title page credits John August and identifies Columbia Pictures copyright. The original is stored locally at `data/scripts/Big-Fish.fountain`, not vendored into Git. The committed `data/sources/big-fish.json` records URL, provenance and SHA-256 so the same input can be retrieved and checked.

```bash
curl --fail --location https://fountain.io/_downloads/Big-Fish.fountain --output data/scripts/Big-Fish.fountain
php bin/console app:load data/scripts/Big-Fish.fountain
```

Create `data/scripts/` first on a fresh installation. Verify the download against the recorded SHA-256 before importing. The current parser imports 198 scene records (including its opening segment) and 53 character records. These are parser output counts, not an independently verified authoritative breakdown. Big Fish exercises more Fountain features than our initial fixtures; this import does not establish full format conformance. Its multiline title-page notes are now handled correctly.

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
