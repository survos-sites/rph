# Wizard of Oz: Symfony scene prototype

Open https://rph.wip/scripts/the-wizard-of-oz/scenes/the-wizard-of-oz-1/play.

This first prototype lives in RPH. It provides four ordered scenes, dialogue and stage/delivery directions, simple original SVG portraits for Dorothy, Scarecrow, Tin Man, Lion and Toto, scene selection, block stepping, Play/Pause/Restart, and cached free local voices. The JSON endpoint at the same URL plus `.json` is the boundary for a later Unity client. Source script text stays in Symfony.

## Recovered source

No Oz Celtx file was found in the inspected local trees. The original Celtx desktop sample was recovered from `n8willis/celtx`, `mozilla/celtx/app/profile/CeltxSamples/1_TheWizard.celtx`, pinned to the commit recorded in `var/script-recovery/originals/celtx/source.json`. The archive SHA-256 is `d25a72cb0645ffabfed4bbebc760cca3febc3aee45416ba954a47928858c9a51`. Local file: `var/script-recovery/originals/celtx/the-wizard-of-oz.celtx`.

The archive contains a film screenplay (`script-KEW.html`) and a separate stage adaptation. The importer selects the film using `project.rdf`'s `ScriptDocument` and `localFile`. Its four scenes are Forest/Day, Forest/Night, Rippling Brook/Day and The Great Ditch/Day. It has 41 dialogue blocks, 18 action blocks and 5 parentheticals; four speaking roles. This is Celtx HQ's sample adaptation of Baum, not the complete 1939 film screenplay. Source metadata says “celtx where applicable”; preserve that provenance and review rights before public redistribution.

## Parser decision

Inspected `survos/screenplay-parser` at `e7ed71bf7e526a31b46b7fdde0d1e8de2bd692ba`. Its `Screenplay\Extractor`/`Filters\Celtx` provides `parse_scenes()`, `parse_characters()` and `parse_capitalized()` breakdown lists. It deduplicates headings and characters, omits cues with parenthetical suffixes, reads at most 1 MB per entry with legacy ZIP functions, and picks the last `script-*` archive entry. It does not produce ordered dialogue/action paragraphs, and the top-level Extractor suppresses parser exceptions. Installing it unchanged would not satisfy scene playback.

`App\Service\CeltxParser` uses current PHP `ZipArchive` and DOM to emit RPH's existing `ParsedScript → ParsedScene → ParsedElement` types. It reads entries without extracting archive paths, bounds document size, blocks external XML network loading, selects the film document deterministically, preserves duplicate headings and paragraph order, keeps inline cast text and `<br>` spacing, normalizes delivery suffixes, and retains speaker links on parentheticals. Missing/ambiguous documents and orphan dialogue produce errors. A readable Fountain representation is stored in the existing script content field.

## Reproduce locally

Requires PHP DOM/ZIP, the app's configured database and (for voice generation) macOS `say` plus FFmpeg.

```bash
php bin/console app:load var/script-recovery/originals/celtx/the-wizard-of-oz.celtx
php bin/console app:voices the-wizard-of-oz
```

The original `.celtx` and cached speech remain local. `data/cast/the-wizard-of-oz.json` contains only display/voice configuration. The five SVG sketches are original project assets. The scene JSON always comes from the persisted RPH entities, not a second script copy.

Speech adapter: Samantha (Dorothy), Fred (Scarecrow), Ralph (Tin Man), Daniel (Lion), rate 165. Each dialogue block has its own MP3, content-addressed by voice/text/version/rate; replay does not call a service. Action and parenthetical blocks are displayed, not spoken. If a voice is absent or playback fails, the reader remains usable with Voice off. Toto has no invented dialogue. No AI service, asset store purchase or paid API was used.

## Verification

- Four HTML scene-player pages and four JSON routes returned HTTP 200.
- All 41 dialogue blocks have valid local audio URLs and positive decoded duration.
- Browser verified Next updates dialogue and character highlighting; Play starts decoded audio (`paused=false`, `readyState=4`, advancing time); Pause stops it; scene selection loads Scene 4.
- Cross-script scene request returned 404.
- Five parser tests / 23 assertions pass, including repeated heading preservation, nested text, parenthetical ownership, RDF film selection and empty-speaker Fountain regression.
- Twig/container lint and Doctrine schema validation pass. Browser console had no errors/warnings.

## Next: Unity consumes this prototype

Do not move script ownership into Unity. Export these same scenes plus cast recipes, cached audio and separate directorial cues. Keep source dialogue intact. The first 3D adapter can use free script-built figures, gaze/head/arm gestures and two shoulder shot rigs. Complex directions need explicit blocking, props and reviewed animation mappings; a text note is not evidence that a matching animation was implemented.

## Free Unity adapter delivered

`php bin/console app:scene-pack the-wizard-of-oz var/oz-scene-pack.json` exports the persisted graph, cached speech durations, avatar recipes and separately authored `data/direction/the-wizard-of-oz.json`. Runtime source, tested macOS app and four complete videos are preserved at `/Users/tac/unity/folio-demo/oz-demo`. The primitive avatars and stage are created entirely in C#. Mapped gestures, deterministic blocking and two shoulder views are intentionally coarse. RPH remains the script authoring and review surface.

The Unity player is now an independent project at `~/unity/RolePlayhouse`, with product name Role Playhouse and macOS app `Builds/macOS/RolePlayhouse.app`. The temporary SurvosOz addition has been removed from MuseumBrowser; its original Git tree is clean.

## Symfony shotlist

Open `/scripts/the-wizard-of-oz/scenes/the-wizard-of-oz-1/shotlist`. This is the preferred low-resource prototype: 64 static JPEG references across four scenes, about 3.4 MB total, linked to persisted script elements using `data/shots/the-wizard-of-oz.json`. Still images were sampled once from the already completed prototype frames. No new render, animation, Unity process or AI call is needed to read or play the slideshow. Dialogue subtitles and direction notes come directly from RPH and can change independently of images. Previous/Next, shot selection, scene selection and timed slideshow are available; local voice is optional and defaults off. Each script block has one draft reference, not a finalized cinematic cut.

Arrow keys change shots. Actions appear in italics above dialogue on the image; the viewer fills the viewport without a scrolling shot list.

Celtx production metadata is now imported and available under `/scripts/the-wizard-of-oz/production`. See [production and shot language](production-and-shot-language.md) for the data boundary, formal shot grammars, Scenea intent and deferred voice/UMA experiments. Current checks: seven tests / 40 assertions, Twig/container lint and schema validation.
