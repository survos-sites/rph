# RPH legacy preservation inventory

This inventory records the useful product ideas and implemented behavior in
`/home/tac/sites/rph-5` before that application is retired. The legacy checkout
should remain available as an archival reference until the items below have
either been rebuilt, explicitly deferred, or deliberately retired.

The goal is not a line-for-line Symfony port. It is to preserve the work and
the product thinking while rebuilding it with Symfony 8.1, AssetMapper,
Stimulus, Tabler, current Survos bundles, and AI where that now makes sense.

## What the product was trying to do

RPH was more than a Shakespeare browser. Its core idea was a canonical,
changeable electronic script for a theater company: actors could rehearse from
their own role-focused view, the company could revise the script and music
without redistributing paper copies, and a production could manage casting,
notes, invitations, and performance state around that script.

The new application already restores a useful public baseline:

- a script catalog loaded from the Bard corpus;
- full-script reading;
- scene-by-scene navigation;
- character pages and role-focused rehearsal views;
- a basic prompter;
- stable entity identifiers in routes;
- Tabler navigation and AssetMapper assets.

That baseline does not yet replace all of the legacy playhouse.

## Preservation matrix

| Capability | Legacy implementation/evidence | Current disposition |
| --- | --- | --- |
| Fountain import and normalized script model | `FountainParser`, `AppService::importFountain()`, `textToScript()`, `scriptToFountain()` | Partially rebuilt. Add parser-parity fixtures before retiring the old parser. |
| Final Draft XML (`.fdx`) import | Working code is in `AppService::importFinalDraftFdx()`; the separate `FinalDraftService` is empty | Preserve as a modern importer service with fixtures. Do not preserve the empty shell. |
| Original and parsed text | `ScriptText` stores Fountain and JSON representations | Preserve the distinction between source, normalized model, and derived output. |
| Script reader | Full viewer, format choices, paging, maximum line count, start position, one-page and embedded views | Baseline rebuilt; preserve pagination, deep linking, formatting controls, and embeddability as later UX. |
| Scene walkthrough | Scene-aware viewer and contextual navigation | Baseline rebuilt. Preserve previous/next navigation and scene progress. |
| Character rehearsal | Character slideshow, highlighted lines, other-speaker/lead-in classes, scene filtering | Baseline rebuilt. Still restore lead-in cues, hide/show other dialogue, scene filters, and rehearsal progress. |
| Prompter | Scrollocue-based prompter and custom JS | Baseline rebuilt. Replace the old plugin with native controls, speed, font size, pause, fullscreen, and eventual offline support. |
| Presentation modes | Script and character slideshows, split-screen route, QR/customized viewer | Preserve the use cases; rebuild with modern browser APIs rather than porting the old JS. |
| Character profiles and avatar generator | `Character.attributes`, `avataarUrl`, YAML/JSON property definitions, live form-driven preview | Preserve the editable profile concept. Replace the generator with evidence-grounded AI dossiers and optional generated portraits. |
| Script annotations | `ScriptNote`, `NoteType`, line number, character, type, score, color/icon; line-scoped note UI | Preserve. This is a strong next domain slice after the reader is stable. |
| Music and audio cues | Scene-coded filenames, Dropbox browser/import, embedded Amplitude player, `Music` entity | Preserve scene-linked audio and cue playback. Retire Dropbox-specific plumbing; use current storage abstractions/object storage. |
| Teams and membership | `Team`, `Member`, roles, visibility, invitations and contextual menus | Preserve the domain design, but authentication and tenancy can remain deferred. |
| Productions and casting | `Production`, `CastMember`, character assignment, schedule/duration, conference URL | Preserve as a later collaboration slice. It is central to the original playhouse, not incidental admin code. |
| Script lifecycle | Script workflow for text setup, character definition, preview, publication and revisions | Preserve the lifecycle semantics; simplify the state machine when it is reintroduced. |
| Production lifecycle | Invitation, request, casting, approval, rehearsal, performance and archive states | Preserve as product knowledge; re-evaluate exact states with the modern production UI. |
| Revision distribution | README describes re-uploading revised scripts/music and notifying members | Preserve as a product requirement. The old implementation is incomplete, so build explicit `ScriptVersion` and change/diff behavior. |
| JSON/API output | Script JSON export and serializer-backed object generation | Preserve as a stable corpus/export API rather than copying the old response shape blindly. |
| Documentation | Sphinx source, README workflows, tutorial concepts and screenshots | Migrate useful product documentation to Markdown; keep screenshots as historical evidence where licensing permits. |

## Legacy workflows: what was actually there

There were two Script workflow YAML files with different graphs, plus a
Production workflow. They should be treated as design evidence, not copied.

### Script lifecycle

The intended places were:

`new -> text -> characters_defined -> preview -> published`, with additional
`revising_text` and `revising_characters` places.

The entity constants and README make that intent clearer than either YAML
file. One configuration instead defined `new -> locked -> characters_defined
-> preview -> published`; the other mistakenly made every transition start at
`new`, leaving the revision places unreachable. Labels also confused actions
and destinations (`revise_text` led to `published`).

The one implemented side effect was important: the Script workflow subscriber
called `AppService::textToScript()` during the `lock` transition, turning raw
text into scenes, elements, and characters. There were no implemented side
effects for publication or revision notification.

A state-bundle rewrite should model observable processing, not editing-screen
navigation. A reasonable first graph is:

`uploaded -> parsing -> parsed -> reviewing -> published`, with `parse_failed`,
`revising`, and `superseded` places. Parsing should be a repeatable transition
handled by a dedicated listener/service. A new source revision should create a
`ScriptVersion`; it should not mutate a published source back through ambiguous
`revising_*` states.

Use a declarative `ScriptFlow` under `src/Workflow` with state-bundle
`#[Workflow]`, `#[Place]`, and `#[Transition]` attributes; put behavior in a
separate transition listener. The entity should use `MarkingTrait` and set its
initial marking. Parser work can be asynchronous later, with an explicit
failure place and import report. Generate and commit the state-bundle workflow
diagram when implemented.

### Production lifecycle

This graph captured two legitimate entry paths:

- a writer invites a company: `new -> invited_by_writer -> invitation_accepted
  | declined`;
- a company requests a script: `new -> script_requested -> approved | denied`.

Approved or accepted productions then moved through `casting -> rehearsing ->
performing -> archived`. Archiving was also allowed directly from rehearsal.
The configuration had no transition behavior beyond marking changes, and the
`perform` transition constant contains the legacy typo `perfrom`.

This is worth rewriting as `ProductionFlow` when productions return. The two
approval paths, casting, rehearsal, performance, and archive are useful. The
exact invitation/request ownership and guards should be redesigned alongside
modern security rather than preserving the old role checks.

## Talented Clementine and legacy fixtures

The legacy repository already contains several tracked Talented Clementine
representations, including Fountain, FDX, text, and XML. The useful point is
format coverage and production-specific conventions; Dropbox itself contains
no indispensable application behavior.

Do not add another full copy to the new Git history without confirming the
rights and desired visibility. Prefer:

- tiny synthetic, checked-in Fountain and FDX fixtures for individual parser
  features and regressions;
- one deliberately reduced Clementine excerpt only if Paul/the project can
  authorize it as a repository fixture;
- a compressed private archival snapshot in storage-bundle-backed object
  storage for the complete historical source and any audio;
- a manifest in Git recording format, checksum, provenance, permission, and
  storage key, but no secret URL or Dropbox token.

The old audio importer inferred a scene prefix from each MP3/WAV filename,
stored filename/path/temporary Dropbox link, and grouped tracks by that prefix.
It was already intended to copy unplayable temporary Dropbox media to S3, but
that dispatch path was disabled. A replacement should use
`survos/storage-bundle` from ingestion onward and model a stable `AudioCue`
linked to a script version and, where possible, a scene or script element.

## Parser parity that must be tested

The legacy Fountain parser recognizes more than dialogue and scene headings.
Before declaring it replaced, create small checked-in text fixtures (not corpus
data) for:

- title-page fields;
- scene headings and explicit scene numbers;
- action, character, parenthetical and dialogue blocks;
- forced transitions and dual dialogue;
- synopsis and section headings;
- comments/boneyards and page breaks;
- the RPH-specific crew-note/transition conventions described in the README;
- song/scene codes used to associate music;
- Fountain-to-Fountain cleanup previously done by `fountToFountain()`.

Round-trip fidelity should be measured separately from successful display. A
viewer can look correct while silently dropping production metadata.

## Domain concepts not yet represented in the new app

These need not all return as the same Doctrine entities, but they should not be
forgotten:

- `ScriptText` / `ScriptVersion`: immutable uploaded source, normalized result,
  import warnings, checksum, revision and provenance;
- `ScriptNote` / `NoteType`: user and production annotations anchored to a
  stable element, not only a fragile line number;
- `CharacterProfile`: canonical facts, editable interpretation, generated
  dossier, portrait/prompt, evidence references and generation provenance;
- `Team`, `Member`, `Invitation`: company membership and role policy;
- `Production`, `CastMember`: a particular staging and its assignments;
- `AudioCue`: scene/element association, label, ordering, media object and
  timing, replacing Dropbox filename conventions;
- explicit script/production workflows and revision notifications.

## Character generator: preserve the intent, replace the mechanism

The legacy generator used a structured set of avatar properties, serialized
the form in JavaScript, and assembled an external avatar URL. That UI encoded
a valuable idea: a character is an editable creative artifact, not merely a
speaker name.

A modern version should generate a structured dossier from the play while
retaining human control:

- canonical facts and quoted/located evidence from the text;
- relationships, objectives, conflicts, changes by scene, and speaking style;
- casting/rehearsal notes clearly labeled as interpretation rather than canon;
- an editable visual description and image prompt;
- optional portrait variants;
- model, prompt, source revision and generation timestamp provenance.

Generated assertions must cite script elements and distinguish textual fact
from inference. Start with one play and one character before generating the
whole corpus.

## Other high-value AI projects

- a role coach that supplies cue lines, explains context, and rehearses a scene;
- character chat constrained to the play, with citations and an explicit
  in-character/analysis mode switch;
- scene summaries, beats, objectives and relationship graphs;
- semantic search across lines, scenes, themes and characters;
- casting breakdowns and production checklists;
- text-to-speech cue tracks and role-omitted rehearsal audio;
- revision diffs that explain which actors, cues and notes are affected.

Token counts already recorded by Bard make a one-play vector/search experiment
a sensible first cost and quality benchmark.

## Less obvious legacy product ideas worth retaining

- The homepage described a writer/actor marketplace: writers publish scenes,
  actors join troupes, and a team moves quickly from script discovery to a
  remote performance. That is broader than the Clementine rehearsal tool.
- The customized reader offered character selection, character-scenes-only,
  skip-dialogue, include-songs, highlighted lines and lead-in cues, followed by
  a QR code for opening that exact view on another device.
- Viewer waypoints tracked the current scene/line and supported next/previous
  movement. This is useful state for rehearsal, notes, audio synchronization,
  resuming a session, and analytics; it should become stable script-element
  addressing rather than DOM position.
- The note taxonomy was rehearsal-specific: missed line, missed/wrong words,
  cannot understand, missed laugh, too slow, and too fast. Colors/icons are
  disposable, but this confirms notes were intended as performance feedback,
  not generic comments.
- Templates explicitly contemplated offline use. A rehearsal reader is a good
  PWA/offline candidate once versioning makes cached-script behavior safe.
- Script fields included visibility, preview visibility, production
  permission, owner and use policy. Even if security is deferred, rights and
  permission metadata belong on imported/non-public scripts and generated AI
  artifacts.

## Implementation details to retire deliberately

Do not port these merely because they exist:

- AdminLTE, Encore, jQuery, jstree and FOSJsRouting integration;
- the bundled Scrollocue and Amplitude demo code;
- the obsolete remote Bard/Heroku API route;
- Dropbox-specific filesystem/token handling;
- custom ParamConverters;
- old OAuth/security wiring and EasyAdmin configuration;
- placeholder `FinalDraftService` and `ImportService` classes.

Their user-facing capabilities are accounted for elsewhere in this document.

## Archival artifacts not to delete yet

Keep these in `rph-5` until their behavior has fixtures or a replacement:

- `src/Services/AppService.php` and `src/Services/FountainParser.php`;
- all legacy entities and workflow configuration;
- controllers and both menu subscribers, which form a route/use-case catalog;
- `assets/js/ScriptViewer.js`, `player.js`, `character_edit.js`,
  `slideshow.js`, `prompter.js`, and the avatar property YAML/JSON;
- script, character, team and production templates;
- `public/scrollocue`, documentation sources, and product screenshots;
- migrations when historical schema details are needed.

The corpus, imported database, generated media, secrets and user data must not
be committed to the new repository. If a reproducible corpus snapshot is
needed, publish it separately with source/license/provenance metadata and keep
only a manifest/checksum in Git.

## Recommended modernization order

1. Lock down parser/import parity with small fixtures and import reports.
2. Finish reader ergonomics: lead-ins, filters, progress, deep links and
   prompter controls.
3. Prototype one evidence-grounded character dossier and one-play vector index.
4. Restore stable annotations and note types.
5. Add storage-backed audio cues and rehearsal playback.
6. Introduce script versions and revision impact/diff reporting.
7. Reintroduce teams, casting and productions only when collaboration is ready.

This sequence keeps the public corpus useful while preserving the distinctive
work that made RPH a playhouse rather than just another script reader.
