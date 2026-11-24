# Handoff: "Christmas, Facing West" trailer

Status as of 2026-11-24. The trailer plays end to end in the browser as one OTIO timeline, with generated voices, a full sound mix and 4 of its 30 pictures. The rest show text cards describing the planned picture.

Watch it: `https://127.0.0.1:8000/scripts/christmas-facing-west/timeline`

## The pipeline

```
data/originals/christmas-facing-west.fountain      archival script, annotated (rph-annotations/1)
  app:load        → database (scenes, elements, notes)
  app:voices      → public/media/voices/<key>.mp3    VoiceStudio, one file per line
  app:pictures    → public/media/generated/…/img/    mflux Z-Image-Turbo, local
  tools/synth-cues.sh, tools/synth-sfx.sh → score and sound effects (ffmpeg)
  app:timeline    → data/timelines/christmas-facing-west.otio
  /scripts/{id}/timeline   survos/otio-bundle Remotion player
```

The OTIO refers to media by stable IDs (`voice:<key>`, `img:<slug>`, `sfx:…`, `amb:…`, `mus:…`). `data/media/christmas-facing-west.json` maps each ID to a file and records where it came from and its licence. To replace a picture or sound, change the manifest, never the OTIO. Generated files live under `public/media/generated/` and are not committed.

| File | What it is |
| --- | --- |
| `data/originals/christmas-facing-west.fountain` | The script, with shot, sound, emotion and timing notes |
| `docs/fountain-annotations.md`, `data/vocabulary/rph-annotations-1.json` | The annotation vocabulary and how it compiles |
| `data/cast/christmas-facing-west.json` | Voice design and seed per character |
| `data/media/christmas-facing-west.json` | Media manifest: every ID, its file, source and licence |
| `data/media/christmas-facing-west.prompts.json` | One image prompt per picture slug, with style and character looks |
| `src/Timeline/FountainTimeline.php` | Compiles the script to OTIO: beats, dialogue trims, pictures, sound beds, lanes |
| `src/Timeline/TimelineMedia.php` | Resolves IDs; draws the text placeholder cards; trims speech; phone/PA/car-speaker effects |
| `src/Command/GeneratePicturesCommand.php` | `app:pictures`: generates missing pictures and records them in the manifest |
| `tools/generate-pictures.sh` | Resumable model download, then generation, then timeline |
| `tools/synth-cues.sh`, `tools/synth-sfx.sh` | ffmpeg score and effects, loudness-normalized |

## What happened, briefly

1. The treatment became an annotated Fountain script. Every element carries notes for shot, emotion, pace, sound and timing.
2. Lines were voiced with VoiceStudio (OmniVoice), and `app:timeline` assembled one playable trailer.
3. Sound: 18 missing effects were filled in. 3 are from the HeyGen catalog and 11 are synthesized. 3 (TV, speakerphone, crowd) are built from the script's own voices, reversed and filtered. The shop radio is the store carol through a small-speaker filter.
4. **Course correction (user feedback).** The trailer had drifted into "an unrecognizable mess", in two ways:
   - **Pictures** looked like agency headshots. Three HeyGen character portraits had been used as stand-ins for story shots, and the prompts asked for gritty realism. The target is the look of the earlier Codex boards (`~/Documents/Codex/2026-11-23/cre/outputs/christmas-facing-west/assets/storyboard.png`, `teaser-board.png`): warm, saturated Christmas-movie stills, with Jess always in a camel coat and red scarf, and Ben in a denim work shirt with a shop rag. The prompts now aim for that, and the headshot stand-ins are out of the manifest.
   - **Dialogue** was writer's shorthand that didn't survive being heard: "Comes from Tesla", "Gets cold, shuts down", a running Tyler reference, trailing half-lines and fast delivery. Every line was rewritten as a plain, complete sentence, and the pace was slowed. All 30 lines were re-voiced, and Whisper transcribed each one word for word. The production notes in the script now carry the rule: every line must make sense heard once, over a phone speaker.
5. Pictures: Z-Image-Turbo (Apache 2.0, about 20 GB, in the Hugging Face cache) generated 4 on-style shots: office, car, Main Street, snowman yard. Generation was stopped at the user's request because it used too much of the machine (about 90 s per picture, 100% GPU).

## Open items, in order

1. **Re-voice the trailer (needed before the next `app:timeline`).** Commit `3c3ec3c` (the animatic work) changed `LineVoice` to join instruction parts with `, ` instead of `. `. That changes the cache key of 23 of the 30 lines. The committed `.otio` still points at the old files and plays. Rebuilding first would drop those lines with a "run app:voices" warning. Fix:

   ```bash
   bin/console app:voices christmas-facing-west && bin/console app:timeline christmas-facing-west
   ```

   Then re-check intelligibility. The Whisper check is a scratch script: decode each MP3 with ffmpeg to 16 kHz float, and run faster-whisper large-v3 (cached) through `uv run --with faster-whisper`.

2. **Uncommitted fix: sound cues on dialogue lines.** `FountainTimeline::build()` only fired `sfx`/`amb`/`mus` notes on action lines, so cues on dialogue were ignored:
   - `keyboard:out` on "Mom? Is that a siren?" never fired, and the typing ran for 51 seconds. The user noticed this: "the background typing never stops".
   - `tannenbaum:out, theme:swell` on the crowd countdown was ignored too.

   The fix (two lines, in the working tree, tests pass) fires a dialogue line's cues as it starts. It takes effect on the next `app:timeline`, after item 1.

3. **Generate the remaining 26 pictures** when the machine is free:

   ```bash
   HF_HUB_OFFLINE=1 bin/console app:pictures christmas-facing-west
   ```

   It skips finished pictures, has no timeout, and saves the manifest after each one. Use `--only=slug` and `--seed=N` to redo a shot. Afterwards, look at every picture before showing the trailer: faces, hands, wardrobe consistency, stray text. Text-only generation keeps the characters similar but not identical. For true consistency, an edit model with a reference image is the next step (Qwen-Image-Edit is in mflux, another large download).

4. **Length.** The plainer lines run longer: 99 s against the planned 71. Tighten the scene `dur=` values and trim. The "Any news on the part?" montage (#07) and Dot's line are the first candidates.

5. **otio-bundle.** rph's `vendor/survos/otio-bundle` was symlinked to `~/sites/mono/bu/otio-bundle`. The mono fixes are uncommitted: the shared audio-tag pool, proportional title-card pacing, and the `wipe` equal-range guard. Release them, then restore normal vendor linking; `composer install` undoes the symlink. A "plain" picture layout without the gold bar would also help.

6. **Sounds to replace first:** the crows (rough synthesis), then the wrench, sleigh bells and crowd. HeyGen catalog files are marked "not cleared for use outside HeyGen" in the manifest.

7. **Cleanup:** `christmas-facing-west-teaser.md` (the first Markdown draft) got committed in `3c3ec3c` and can be deleted. The Fountain file is the source.

## Related work elsewhere

- **Rutado story narration** (separate session, done): zm's `story:voice` can use the chosen VoiceStudio narrator (`omnivoice-2101`). *Places Please!* is re-voiced, public on S3, and its Story record on pg.survos.com points at the new audio. The zm code change and the rut plan note (`~/sites/rut/docs/PLAN-story-movies-and-voices.md`) are not committed. The plan proposes extracting `survos/voice-bundle` (rph `src/Voice` plus zm's OpenAI/S3 code) and `survos/story-bundle`.

## Lessons

- Look at the result as a viewer before adding layers. Every component passed its own checks while the whole drifted.
- Verify dialogue by transcribing it, not by reading the script.
- Reference portraits are for consistency, not for shots.
- Two sessions working in the same checkout will sweep each other's uncommitted changes into their commits. Commit before handing off, or use a worktree.
