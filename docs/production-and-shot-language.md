# Celtx production data and shot language

RPH owns scripts and review. Unity consumes exports and can later return stills. Voice providers and avatar builders remain replaceable adapters.

## Celtx import

Open https://rph.wip/scripts/the-wizard-of-oz/production or its `.json` endpoint. The original desktop sample provides four scene records, four character profiles, three breakdown items, nine storyboard shots in the first scene, three layout drawings and 27 media records. Source HTML/RDF identifiers bind scenes and characters. Department membership connects scenes to cast, props, sounds and production locations. The authored storyboard is distinct from our 64 draft stills attached to screenplay paragraphs.

`Script.projectMetadata` stores the merged graph with repeated RDF descriptions and full predicate identifiers. `ProductionDataService` projects selected creative fields, media URLs, storyboard order, layout geometry and bindings for the page, scene JSON and `app:scene-pack`. Actor/acquisition contacts and the raw graph stay out of that projection.

ZIPs and media live under `var/celtx/<archive-sha256>/`, outside the public directory. A manifest-checked controller serves media. SVG previews retain allowed geometry and remove scripts, external references and event handlers; the original remains in the ZIP. Deployments need the database and this directory, or a reimport. Run migrations before importing.

Some source notes are Celtx tutorial text. Shot sizes map to ECU/CU/MCU/MS/MLS/LS/ELS without inventing positions. SVG coordinates are drawing units; symbol fingerprints are not actor/camera identifiers. Unity placement requires reviewed symbol mappings and a scale conversion. Old Factory is a production location, not the fictional forest.

## Existing formal languages

**Prose Storyboard Language (PSL)** is the closest match: a formal grammar for composition, subjects, camera operations and actor actions. Its original paper specifies EBNF; PSL 2.0 specifies a PEG grammar and concurrent temporal semantics.

- [Original PSL paper](https://cdn.iiit.ac.in/cdn/cvit.iiit.ac.in/images/JournalPublications/2015/vineeth_storyboard.pdf)
- [PSL 2.0 publisher PDF](https://diglib.eg.org/server/api/core/bitstreams/4e584855-c391-488a-a7f9-fa0bfff49fcb/content)
- [Reference parser linked by the paper](https://gitlab.inria.fr/vmurukut/psl): access hit a bot challenge; code was not inspected.

**Declarative Camera Control Language (DCCL)** directly connects to Arijon: its paper encodes 16 idioms from *Grammar of the Film Language*, expressing camera placement and editing constraints. **The Virtual Cinematographer** uses hierarchical state machines for directing idioms, including conversation/reaction coverage. **Embedded Constrained Patterns** describes patterns across shot sequences.

- [DCCL, AAAI 1996](https://grail.cs.washington.edu/wp-content/uploads/2015/08/dccl-aaai96.pdf)
- [Virtual Cinematographer, SIGGRAPH 1996](https://grail.cs.washington.edu/wp-content/uploads/2015/08/he-1996-tvc.pdf)
- [Embedded Constrained Patterns](https://cinematography.inria.fr/files/2017/08/paper1004_CRC.pdf)

Recommendation: borrow PSL vocabulary, store typed JSON, and offer short editor aliases. Illustrative RPH aliases, not standardized PSL syntax: `CU(Bob)`, `OTS(from=Sally,to=Bob,size=MCU)`, `TWO(Bob,Sally,size=MS)`. OTS needs both foreground shoulder and framed subject. Shots can span several dialogue blocks; speaker changes do not automatically imply cuts. No formal-language parser has been implemented yet.

## Scenea

Scenea was intended to detect sharp visual breaks between frames, recover a film's original cuts and reconstruct a multi-track edit, then drive an animated recreation with that coverage and timing. Historical source is retained at `~/sandesa/scenea`, extracted from nested `sandesa/vt/scenea`; it is not a confirmed modern port.

Keep screenplay paragraphs, authored storyboard shots and future film-derived shots/timecodes independent. This Oz source is a four-scene Celtx sample, not the complete 1939 film. Cut detection and film/script alignment are deferred. Independent shot IDs and provenance prepare for later work.

## Later voice and avatar experiments

Compare four short voice clips in Symfony: neutral, whispered, interrupted and shouted. Cache by provider/model/voice/text/delivery and measure duration; Unity reuses those files. Keep dialogue verbatim and delivery instructions separate. Current macOS voices remain the free baseline; no paid calls were made.

[AssemblyAI's voice catalog](https://www.assemblyai.com/docs/voice-agents/voice-agent-api/voices) exposes voices through its Voice Agent API. This is documented as a conversational interface: verify exact-text offline generation before treating it as a batch TTS replacement. Transcription for rehearsal or film alignment is a separate possible use.

[UMA](https://github.com/umasteeringgroup/UMA) is free and supports character creation/customization. [Current releases](https://github.com/umasteeringgroup/UMA/releases) include runtime avatar presets and wardrobe workflows. Test one humanoid, built through C# from an RPH recipe containing race, DNA, colors, wardrobe and expression references. Cache resulting stills for Symfony and regenerate when the recipe changes. Measure generation time and expression quality. Tin Man, Scarecrow, Lion and Toto may need specialized meshes; UMA is not automatically suitable for every Oz character. No package was installed for this experiment.


## Pause-point decision

Cine-AI is the selected architectural lead, backed by a richer Symfony-owned shot corpus. See the [detailed handoff](scene-player-handoff.md) for the source audit, Unity tools, voice options, PROSE language limitations, and resume plan.
