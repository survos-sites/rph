# Screenplay, directing annotations, and rehearsal are linked layers

Research clarification, 8 October 2026.

## PSL 2.0 includes speech and actor actions

The name is **Prose Storyboard Language (PSL) 2.0**. Figure 15 of the [paper](https://diglib.eg.org/bitstream/handle/10.2312/wiced20221048/013-027.pdf) includes speech events, optionally with a quoted string and addressee, alongside entrances/exits, gaze, movement, touching, object use, reactions, turns, stops and appearance/disappearance. Events can trigger reframing or occur concurrently with camera development. It is more expressive than a list of shot sizes.

It is not a complete production/rehearsal interchange format. Avatar recipes, asset catalogs, generated speech provenance, recordings, recognized words and performance feedback need their own records. A grammar naming a reaction does not specify facial rig values or implement the reaction.

Celtx also represents screenplay dialogue/actions and links production breakdowns, storyboards and shot blocking. See its [script editor](https://support.celtx.com/hc/en-us/articles/360009310173-The-Film-TV-Script-Editor), [breakdown](https://support.celtx.com/hc/en-us/articles/216581678-Breakdown), and [storyboard/shot-blocker](https://support.celtx.com/hc/en-us/articles/216076228-Storyboard-and-Shot-Blocker-Film-TV) documentation. Our actual importer reads the old desktop Celtx ZIP/HTML/RDF source model; that should not be confused with full support for the current hosted product.

## Our model is our own, informed by existing formats

RPH's Script, Scene, Character and ScriptElement entities and `survos.scene-pack/1` JSON are application-specific structures. They are not an implementation of PSL's grammar or a standard encompassing all Celtx production data. Imported creative metadata and source bindings now preserve substantially more of the Celtx project. The richer reviewed shot schema in the handoff remains a proposal.

Prefer a project package over a single supersized screenplay syntax:

1. Fountain remains the readable screenplay, exchangeable with Bard and existing writing tools.
2. Stable beat/element IDs connect dialogue/actions to production records, shots and timed events. Filename/sequence IDs in the current prototype need a migration strategy before editing/reordering can preserve annotations reliably.
3. PSL supplies a directing vocabulary and optional original annotation text. Parsed camera/action events can enrich typed JSON without forcing all production fields into PSL.
4. Character/prop/layout/asset records supply realization details and provider-independent media references.
5. Rehearsal sessions reference expected script beats and retain separate observed recordings, recognized words and alignment/feedback results.

This lets the static viewer, Unity player and actor rehearsal tool use the same script while storing different outputs. Preserve original source text, imported annotations, reviewed adaptations and observed actor speech as distinct data.

## Confirmed PSL annotation corpus

The [authors' accompanying-material page](https://team.inria.fr/anima/prose-storyboard-language/) links videos, screenplays and SRT annotations for Rope, the Touch of Evil opening, North by Northwest's crop-duster scene, and Back to the Future's cafe scene. This corrects the earlier handoff's statement that no downloadable annotation corpus had been verified. The reference parser repository remains separately unverified behind its access challenge.

All four SRT files were downloaded and pinned in `data/sources/psl-annotations.json`. Reproduce the private local inputs with:

```bash
python3 tools/fetch-psl-annotations.py
```

Originals live under ignored `data/psl/`; film/video files were not downloaded. No explicit annotation reuse license was verified, so the source manifest is committed rather than the full originals. The paper's reported 177 shots and 330 compositions are its evaluation statistics; downloaded SRT cue counts describe a different unit and must not be used as shot counts.

| Input | Timed annotation cues detected |
| --- | ---: |
| Rope | 23 |
| Back to the Future | 56 |
| Touch of Evil | 47 |
| North by Northwest | 175 |

SRT entries are sometimes sentence fragments spanning adjacent cues. One shot may contain several compositions and developments; a pan is not a cut. Preserve cue IDs, timestamps and raw text before interpreting them. The Back to the Future sample contains `00:00:60` timestamps, and Rope's numbering has a gap. A tolerant time reader should normalize overflow seconds with a warning, accept timestamps with/without milliseconds, and preserve source identifiers rather than assume contiguous numbering. Formal PSL parsing may require joining adjacent continuation fragments; do not label every SRT block a complete valid PSL sentence.

A useful first viewer could step through these annotations with previous/next and arrow keys, showing current framing, subjects, camera/event descriptions, original text and time range. A timeline can use separate composition, camera-development, actor-event and dialogue tracks. Show supported events as images or simple diagrams; display unsupported ones as labeled timed steps. Do not silently discard motion/action just because animation is absent. No new PSL timeline UI or semantic parser has been implemented yet; the fixtures and provenance are now available to build it.

## Historical ScriptAssistant-related code and port status

The closest verified speech-to-script feedback implementation was found in the old Smokescreen archive, principally `sandesa/vt`, revision `888e61577d98dc440c1b1876c14b7a76b1299b43`:

- `cmusphinx/recognize-script.pl`: reads timed recognized words and screenplay JSON dialogue, normalizes tokens, retains scene/paragraph IDs, and uses Needleman–Wunsch alignment to report matches/gaps and a score.
- `bid/process_wav.php`: converts media through LAME/FFmpeg/SoX, runs Sphinx4 recognition, and stores word timestamps and pronunciation information.
- `bid/wordmatch.php`: compares transcript/recognized words, displays matches/mismatches with timing, and computes phoneme differences.
- `vim/script.php`: character selection/highlighted dialogue in the historic script display/export flow.

These establish the recognition/alignment/review components. The inspected matcher and media processing scripts are file/batch workflows; they do not establish that the exact realtime microphone UI remembered by the user has been located. The independent historical name/package “ScriptAssistant” remains to be conclusively mapped to those components.

Current `~/sites/rph` has role-focused rehearsal (`templates/script/rehearse.html.twig`), prompter, scene navigation and cached local TTS playback. It does **not** have microphone capture, streaming line tracking, recognized-word alignment, actor-feedback records or rehearsal recordings. Therefore that tool is not all in RPH now.

The adjacent modern `~/sites/vt` is VideoThoughts: its documentation inventories interviewing/recording/transcript concepts, while the modern capture/transcription workflow is still planned/scaffolded. An exact local Voxstory checkout was not located during this inventory. Do not assume Voxstory and VideoThoughts are the same project or that their capabilities have been verified interchangeably.

A shared future speech layer could provide recording, word-timed transcription, speaker information and review. Actor rehearsal adds known-script alignment, an expected role, cue position, omissions/substitutions and prompting; oral history primarily follows what was said without requiring a known script. ASR mismatch is evidence to review, not proof an actor got a line wrong. These should be distinct applications of shared speech primitives.
