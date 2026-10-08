# Role Playhouse: Cine-AI with more data

Research and pause-point handoff, 8 October 2026.

## Decision

Cine-AI is the strongest starting point for the scene player we want: reusable camera techniques, director-style profiles, and playback driven by a storyboard. RPH should supply a richer, reviewed corpus and own the scripts, characters, production data, shot decisions, and APIs. Symfony remains the primary development surface. First make directing decisions legible in a static shotlist; later use Unity to realize the same decisions in 3D.

The immediate product is a lightweight screen/dialogue viewer: arrow keys advance shots, actions appear in italics above the image, dialogue appears below it, and the frame stays on screen without scrolling. A shot is an editorial decision, not automatically one screenplay paragraph. We already have the viewer; its current paragraph-based stills are a provisional illustration of the interface.

This handoff records a direction, not a completed Cine-AI integration. No Cine-AI, UMA, Genies, or paid speech SDK has been installed. No new rendering service is required to leave the current prototype usable.

## What is already working

RPH lives at `~/sites/rph`, [survos-sites/rph](https://github.com/survos-sites/rph). The independent Unity application lives at `~/unity/RolePlayhouse`, [SeriousUnity/role-playhouse](https://github.com/SeriousUnity/role-playhouse). It is a separate project from Museum Browser. Its runtime stage, primitive characters, gestures, cameras, and dialogue playback are built in C#.

The RPH Oz prototype includes four scenes, 64 screenplay elements, 41 dialogue clips, 18 action elements, and five parentheticals. The static viewer has 64 JPEG stills, about 3.4 MB, extracted from the existing Unity renders. Voices are off by default. Left/right arrows, scene and shot selectors, and slideshow playback are available. These stills are not a reconstruction of the original movie's cut list.

Recovered scripts include Clementine, Bob/Sandy, and jokes alongside Oz. The original desktop Celtx Oz project is retained privately under `var/script-recovery/originals/celtx/`. It is a four-scene Celtx sample adaptation of Baum, not the complete 1939 film screenplay.

Celtx import now preserves four scenes, four character profiles, three breakdown items, nine authored storyboard shots, three SVG layouts, and 27 media records. All nine authored shots belong to the first scene. The breakdown includes Oil Can, Old Factory, and Growl. Source identifiers and department memberships survive import. Old Factory is a production location, not the fictional forest. Some source notes are tutorial instructions rather than directorial intent.

The public production projection excludes contact/acquisition details from the retained private source graph. Media serving checks the extracted manifest; SVG layouts are sanitized before display. Layout geometry does not yet identify actor/camera symbols semantically or establish world-unit scale. Those mappings need review before a Unity scene can be generated from them.

Useful local pages:

- [Oz shotlist](https://rph.wip/scripts/the-wizard-of-oz/scenes/the-wizard-of-oz-1/shotlist)
- [Oz production data](https://rph.wip/scripts/the-wizard-of-oz/production), also available as `production.json`
- The scene player route also exposes `play.json`.

`app:load`, `app:voices`, and `app:scene-pack` provide import, voice preparation, and export. The current pack uses `survos.scene-pack/1`, with cast, beats, cues, audio durations, and a production projection. See [the existing prototype notes](oz-scene-prototype.md) and [production/shot-language research](production-and-shot-language.md).

The last implementation validation passed seven PHPUnit tests with 40 assertions, Twig/container checks, and schema validation. The Unity smoke check verified four scenes and 41 voices. The [v0.1.0 release](https://github.com/SeriousUnity/role-playhouse/releases/tag/v0.1.0-oz-prototype) preserves the macOS application, four videos, and demo inputs. Audio and original import assets have separate storage/provenance requirements; the SQL database is not reconstructed merely by checking out source.

## Cine-AI: what we can actually reuse

Primary sources: [MIT-licensed repository](https://github.com/inanevin/Cine-AI), [research paper](https://aaltodoc.aalto.fi/server/api/core/bitstreams/9d9bdd07-7628-4028-bd97-0916a34cb2fa/content). Source inspection was pinned to commit `4ee0953fe74a0e5d3337e027bc56523f844cfd27`. Upstream was authored with Unity **2020.2.2f1**, Timeline 1.4.5, and TMP 3.0.1. Our application uses Unity **6000.6.4f1 / URP 17.6.0**. Compatibility has not been build-tested here.

The paper reports analysis of 160 movie clips associated with Tarantino and Guy Ritchie. The public repository contains two JSON director profiles, `Tarantino.json` and `Ritchie.json`. These hold aggregate technique values, pace, and dramatization settings. The inspected tree does not supply a large CSV collection of aligned screenplay/shot records. We should distinguish the paper's analyzed material from the data actually downloadable in the repository.

Four technique families are represented: positioning, look, track, and effects. Examples include close-up, master shot, pan, stationary/zoom choices, tracking variants, and slow motion. Explicit over-the-shoulder semantics and a complete dialogue-coverage vocabulary need to be added or mapped by our adapter; two profile files do not establish comprehensive coverage.

`StoryboardData` stores nodes, actor proxies, style settings, simulation frame rate, and camera parameters. `StoryboardNode` links a Timeline marker to a simulated camera position, rotation, field of view, target, and selected techniques. `StoryboardPlayController` receives marker notifications and applies these **precomputed** camera decisions, then updates their tracking/look/effect behavior.

The important boundary is the planner. `StoryboardSimulator` is in the editor subsystem and depends on storyboard-window/editor machinery. Some other files also import/use `UnityEditor` APIs without clean runtime separation. Actor lookup includes names rather than a stable RPH identity. Therefore, runtime playback is available as an architectural example, but our requirement—everything authored/generated by scripts—needs a runtime-safe planner and data adapter. Do not adopt its entire editor workflow as the product.

Source inspection also flags probability handling to review during a port: exponentiating raw technique values can overflow float arithmetic; zero totals need guards. Use stable softmax where appropriate, normalized distributions, deterministic seeds, and explicit fallbacks. These are audit findings, not reproduced Unity failures.

A small port should retain the camera technique ideas, disentangle editor code, bind actors by exported IDs, and create the storyboard from RPH JSON. Keep camera authority clear: if Cinemachine is introduced, decide whether it realizes a selected shot or replaces a particular solver. Two systems must not continuously overwrite the same camera transform.

## “More data” means shot examples, not larger weights

Our valuable asset should be a versioned collection of **script-to-shot decisions and sequences**. A global count of close-ups cannot tell us when to cut to a listener, establish a prop, preserve an eyeline, or withhold a reveal.

A proposed model, not yet implemented:

| Record | Required information |
| --- | --- |
| Source | Work/clip identity, language, provenance, license, source version, timebase, annotation author and review state |
| Scene | Script identity, location, cast, props, layout, dramatic context and line of action |
| Shot | Stable ID, order, start/end time where known, covered script-element IDs, framing, camera side/angle, subjects and foreground subject |
| Blocking | Actor positions, orientation, gaze targets, entrances/exits, prop attachment and coordinate-system/scale definition |
| Motion | Static/pan/tilt/dolly/track, start/end constraints, lens/FOV where known, duration and transition |
| Performance | Speaker, dialogue, delivery, gesture, expression, timing and overlaps |
| Intent | Reaction, reveal, establishment, emphasis, interruption, relationship shift; rationale, confidence and human review |
| Style profile | Versioned derived distributions conditioned on scene context, with sample counts and provenance |

Represent `OTS(Sally → Bob)` with Bob as the framed subject and Sally as the foreground shoulder. Keep `CU(Bob)` a shorthand for a structured framing choice, not a command that magically chooses a valid camera placement. A two-shot, reaction shot, insert, and establishing shot should have explicit identities. Preserve original source terms alongside normalized vocabulary.

A proposed RPH interchange example:

```json
{
  "id": "oz-forest-shot-03",
  "sceneId": "oz-forest",
  "scriptElementIds": ["beat-07", "beat-08"],
  "framing": "medium_close_up",
  "subjectIds": ["dorothy"],
  "foregroundSubjectId": "scarecrow",
  "camera": {"kind": "over_the_shoulder", "axisSide": "A", "movement": "static"},
  "intent": "reaction",
  "actionCues": [{"actorId": "dorothy", "kind": "look_away"}],
  "reviewState": "draft"
}
```

This is a proposal for our schema, not Cine-AI's existing JSON format. It needs validation and a version before implementation. Dialogue/audio remain linked records rather than being duplicated into every shot. One shot may cover several lines; a silent reaction may occur during another person's speech. Separate speech timing, action timing, and camera cuts.

Start with reviewed English examples from the recovered RPH scripts and the Celtx Oz boards. Build neutral conversation coverage before attempting a named director's style. Public-domain Shakespeare supplies scripts, not automatically a professionally annotated shot corpus. Record who devised each shot and why. More film annotations can be added when their availability and redistribution rights are established; a publicly viewable film is not automatically redistributable training material.

Use the static viewer to compare proposed and reviewed sequences, correct cut points, and inspect continuity. Once that is useful, derive Cine-AI-compatible aggregate profiles from the richer records. Keep source annotations authoritative and make aggregates reproducible.

## Existing languages and datasets

[PSL 2.0](https://diglib.eg.org/bitstream/handle/10.2312/wiced20221048/013-027.pdf) is a useful formal vocabulary: a PEG grammar and temporal semantics describe shots, subjects, and concurrent action. The paper describes a Python Parsimonious reference parser and timecoded SRT annotations, with worked film excerpts. [Parsimonious](https://github.com/erikrose/parsimonious) is available; automated access to the [reference PSL repository](https://gitlab.inria.fr/vmurukut/psl) was blocked by its access challenge. We have not verified a maintained downloadable parser or a broad public PSL corpus. Treat PSL as an optional import/export vocabulary rather than a mandatory dependency.

Earlier approaches are relevant because they encode directing conventions: [DCCL](https://grail.cs.washington.edu/wp-content/uploads/2015/08/dccl-aaai96.pdf) formalizes Arijon-inspired idioms; [The Virtual Cinematographer](https://grail.cs.washington.edu/wp-content/uploads/2015/08/he-1996-tvc.pdf) uses hierarchical state machines; [Expressive Cinematography Patterns](https://cinematography.inria.fr/files/2017/08/paper1004_CRC.pdf) describes patterns across shots. These complement Cine-AI's technique selection and offer constraints/context that raw frequencies lack.

[PROSE](https://github.com/creDreams/PROSE) is a different project from PSL: paired screenplays and professional-director storyboard tables released alongside SAGE. Its README lists 68 episodes across three productions and a CC BY 4.0 license. The README is bilingual; inspected [My Cure script](https://github.com/creDreams/PROSE/blob/main/data/my-cure/episodes/EP001_script.md) and [storyboard](https://github.com/creDreams/PROSE/blob/main/data/my-cure/storyboards-director/EP001_storyboard.csv) mix Chinese descriptions/directions with English content. CSV headers include scene, visual content, shot size, angle, movement, characters, and dialogue in Chinese. It is not a ready English corpus.

PROSE remains useful for its paired structure. If revisited, import one episode, preserve originals, map controlled shot labels, and store translated descriptions separately with translation provenance and review status. Translation must not silently become the original annotation. No translation or dataset import has been performed in RPH.

## Unity tools and avatar creation

The runtime goal is a small scene player and still-image renderer. A configured project and assets are acceptable; repeated scene authoring and avatar configuration should come from data/C# rather than manual editor operations.

| Tool | Role and cost category | Decision/status |
| --- | --- | --- |
| Existing C# stage/cameras + URP | Working baseline using our project | Keep for the static prototype and regression reference |
| Cine-AI | MIT source; technique selection, storyboard playback | Chosen architectural lead; runtime/Unity 6 port required |
| [UMA](https://github.com/umasteeringgroup/UMA) | Free avatar system; DNA, wardrobe, runtime recipes | Best lead for code-controlled customization; test one humanoid first |
| [Genies Avatar SDK](https://assetstore.unity.com/packages/tools/game-toolkits/genies-avatar-sdk-336166) | Listed free SDK with its own terms/ecosystem | Investigate runtime loading/customization, auth and cloud dependencies before choosing |
| [Cinemachine](https://docs.unity3d.com/Packages/com.unity.cinemachine@3.1/manual/index.html) | Unity camera composition/tracking/blends | Optional execution layer, not a screenplay grammar |
| [Timeline / Playables](https://docs.unity3d.com/ScriptReference/Playables.PlayableDirector.html) | Unity scheduling and marker playback | Build schedules from code/data; don't require scene-by-scene editor authoring |
| [Animation Rigging](https://docs.unity.cn/Packages/com.unity.animation.rigging@6.6/manual/ConstraintComponents.html) | Aim/look and IK constraints | Useful for gaze and hand/prop targets after stable avatar binding |
| [SALSA LipSync](https://crazyminnow.com/docs/salsa-lip-sync/modules/salsa/overview/) | Paid audio-driven lip-sync tooling | Evaluate after voice choice; configure supported facial targets |
| Character/wardrobe/gesture asset packs | Free or paid, per pack | Choose for rig, URP compatibility, runtime access and license; no pack selected yet |

UMA should consume a versioned character recipe: body/DNA values, skin/hair colors, hair/facial-hair slots, wardrobe and asset IDs. Persist recipes in Symfony, validate them against a known asset catalog, instantiate in Unity, and cache portraits/stills. Humanoid customization will not automatically produce a convincing Tin Man, Scarecrow, or Lion; those require suitable meshes/costumes and rigs.

Genies' [integration guide](https://genies.com/blog/genies-avatar-sdk-unity-integration) is a second lead. Verify that the desired operations are available from C#, and clarify persistence, authentication, network requirements, and terms. A free download does not mean an open-source or offline avatar pipeline. Neither candidate has been integrated.

Map reviewed actions to a bounded cue vocabulary: frown, look away, turn toward, pause, point, raise voice. Expressions depend on actual facial blend shapes; gaze and body animation are separate controls. Free text can suggest cues, but reviewed structured cues should drive the player. Light directorial controls should include framing, speaker/listener emphasis, cut timing, axis side, gaze, and performance intensity.

## Voice: local and paid options

The current baseline is macOS `say`, with cached MP3 clips. Swapping a voice is a relatively cheap experiment and should precede expensive character work.

| Candidate | Use | Boundary |
| --- | --- | --- |
| [Kokoro](https://github.com/hexgrad/kokoro) / [82M weights](https://huggingface.co/hexgrad/Kokoro-82M) | Local compact English TTS candidate | Evaluate actual delivery quality; no integration yet |
| [Piper](https://github.com/OHF-Voice/piper1-gpl) | Local speech synthesis | GPL engine; inspect individual voice licenses separately |
| [ElevenLabs](https://elevenlabs.io/docs/overview/capabilities/text-to-speech) | Paid expressive TTS candidate | Evaluate exact scripted clips, cost, rights and caching |
| [AssemblyAI voice APIs](https://www.assemblyai.com/docs/voice-agents/voice-agent-api/voices) | Interesting voice-agent lead | Confirm standalone scripted/batch synthesis suitability before choosing |

Compare the same four short lines: neutral, quiet, interruption, and shout. Keep image and words constant. Measure intelligibility, delivery, duration, latency and cost rather than guessing. Cache by provider/model/voice/language/text/delivery parameters, retain provenance, and update scene timing from returned audio durations. Never place API secrets in exported Unity packs. No paid calls were made for this investigation.

## Symfony-to-Unity boundary

Symfony owns canonical records, editing, validation, import provenance, voice orchestration, exports, and cache/job status. Unity consumes versioned scene packs and produces stills or plays the scene. The existing independent project already proves this boundary at small scale.

The supplied Unity render-worker notes describe a future persistent desktop GPU worker. For RPH, first adapt that idea to one `render-shot` job: scene-pack version, character recipes, layout, camera specification and output size produce a cached PNG/JPEG. Use local files initially, one active job, explicit failures and content-hash invalidation. Transport/storage abstractions can grow later. Do not add the generic museum/coin workflow, RabbitMQ, S3, or a worker database before needed. Rendering requires a graphics-capable process; an unattended batch invocation is not the same as `-nographics` rendering.

Export one reviewed scene with stable actor/prop IDs, explicit coordinate conventions, source-to-beat mappings, shot definitions and audio references. Test round-trip continuity in RPH before replacing its stills. Our first useful Unity improvement can be better static avatar images; animation is a later output of the same model.

## Scenea and the old archive

Scenea's original aim was detecting sharp visual frame breaks, reconstructing a film's cut list and multitrack layout, then reproducing its coverage in animation. Its original source was restored under `~/sandesa/scenea`, with a clarifying README. It has not been modernized. Shot-boundary detection and film reconstruction are deferred; they are a distinct annotation-ingestion path, not a prerequisite for the Symfony viewer.

The old Smokescreen/Sandesa material was expanded into `~/smokescreen` and `~/sandesa`. Confirmed successors can retain stubs rather than duplicate old implementations. RPH has been modernized independently; Bard's presence in that archive was not established. Do not infer missing historic projects or publish the archive wholesale from this handoff. Remaining legacy publication/organization choices are separate work.

## Before further Fountain work

Read the official [Fountain developer resources](https://fountain.io/developers/), especially **FountainRegexes**, and the linked [reference implementation](https://github.com/nyousefi/Fountain). This is a required research starting point for future importer/exporter changes, not a parser replacement performed now.

The MIT reference includes reading/writing, an element model, regex definitions, sample files and tests. Preserve inline styling markup through parsing and handle its display separately. When adapting expressions to PHP/PCRE, verify dialect differences and contextual screenplay rules against the syntax specification and fixtures.

The repository README adds an important qualification absent from the developer page: its default is the line-by-line `FastFountainParser`; the older regex parser is retained for portability/legacy use, and its regexes are not fully compliant with the tests. Consult both implementations and the fixtures before borrowing expressions. Do not assume regex matching alone provides complete Fountain conformance.

## Resume plan

1. Reopen RPH and confirm scripts, Oz production boards, and keyboard-controlled shotlist. Keep the four-scene prototype as a regression fixture.
2. Define and validate the richer shot schema. Hand-author one reviewed English two-person scene with master, alternating OTS, close-up/reaction, and one insert. Preserve line-of-action and timing decisions.
3. Add shot editing and review in Symfony. Make the static viewer render authored shot sequences rather than automatically equating paragraphs with shots. Completion means a silent reaction and a multi-line shot both work.
4. Port a small Cine-AI technique subset into runtime-safe Unity 6 code. Use stable IDs, deterministic selection, numerical guards and tests for camera placement/continuity. Build the player to prove editor dependencies are gone.
5. Add one UMA recipe and cached portrait/shot generation. Compare with the existing figures before broad asset purchases.
6. A/B replacement voices, then decide whether lip sync adds enough value. Keep voice generation independent of rendering.
7. Expand the reviewed corpus, derive versioned context-aware profiles, and optionally import/translate a PROSE sample or add PSL interchange. Revisit Scenea only if actual film-cut reconstruction becomes the active goal.

On return, begin with `git status` in both repositories and read this handoff. RPH's `php bin/console list app` exposes current commands. Unity's `tools/sync-from-rph.py` updates demo inputs; `tools/render-videos.py` is the existing rendering utility. The batch build entry point is `Survos.Oz.Editor.RolePlayhouseBuild.BuildMac`. Consult each command's current arguments before running it.

The work can pause here: existing viewers and the Unity release remain available, while the next implementation starts from **Cine-AI plus a richer Symfony-owned shot corpus**.
