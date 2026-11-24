# OMC production rendering

Open `/productions/xmas` or `/productions/last-train`. Each page reads its
`data/<slug>/project.json` file list and builds the catalog reports and OTIO
timeline directly from those JSON documents. No database or Fountain parsing
is required on this path.

Characters, narrative scenes, locations, objects (props), wardrobe, contexts,
assets and compositions use MovieLabs OMC 2.8 entities and scoped identifiers.
Native scene contexts drive the casting, props, wardrobe and set reports.

RPH-specific data lives in namespaced OMC `customData`: scene elements and
dialogue, voice and appearance notes, shot framing, generation instructions,
and frame-based edit tracks. Unmapped original fields live in `extras` within
those extensions. These extensions are RPH mappings, not standard OMC fields.

`rph.edit/1` supplies frame rate and ordered clips. `TimelineMapper` resolves
asset references, inserts gaps, retains source offsets and creates OTIO tracks.
The existing playback projector and player render the result. Shot thumbnails
seek the player. `.otio`, `.playback.json` and `.json` routes expose the derived
timeline, playback projection and catalog respectively.

The initial catalogs preserve existing dialogue and voice timing. Christmas now
uses authored SVG blocking boards; its opening cuts and keyboard stop were revised
without moving dialogue. Original image choices remain in media extras. Media provenance and
source restrictions remain in asset metadata; inclusion is not a new license.
Generated media must be present at its referenced URL. These catalogs do not
regenerate voices or imagery, and SQLite storage remains a separate concern.

Verification: `php bin/phpunit tests/Omc/TimelineMapperTest.php` checks both
productions against their existing edit timings and resolves the playback
tracks without diagnostics. It also checks scene-linked reports and rejects
unsafe catalog paths.

## SVG storyboard generation

Run `bin/console app:omc:storyboards xmas` locally or during deployment to rebuild
SVG assets. This generic command reads `rph.shot/1.storyboard`: a backdrop name,
an ordered list of character/prop layers with x/y/width/height boxes, pose and
expression, plus a description. Character appearance comes from
`rph.character/1.appearance`. Blocking choices are marked provisional.

The renderer composes reusable `data/storyboards/backdrops` and `props` SVGs
with `PoseRendererInterface` (currently StickyFig). No model, GPU or paid API is
needed. Rendered files use content hashes; rerunning identical plans yields the
same URLs. The command selects their URLs in the media catalog, retaining each
original URL and credit in `extras.originalMedia`. Commit source templates and
JSON; regenerate the ignored media files on the target host before playback.
Node and the vendored StickyFig adapter are required alongside PHP.

The Christmas opening now cuts at frames 60 (desk), 193 (Linda), 279 (Jess reacting)
and 423 (car), at 30 fps. Keyboard audio ends at frame 279, matching the siren
question. Every dialogue clip retains its previous start, trim and duration.
Subtle frame-driven motion is stored on the picture clips. These are blocking
stills with expressions, not animated lips or finished illustrations.

Selective dialogue revisions can be rendered with
`bin/console app:omc:voices xmas --only=03g,04c,05g,06b`.
The command uses the existing local VoiceStudio synthesizer and character voice
profile, with line delivery in `direction.say`. It caches audio by voice/text,
measures the resulting file, resets the selected voice trim, and maps edit/shot
boundaries across the changed interval. Other voice recordings are retained.
Long ambience clips spanning a revision extend or contract with the edit.
Original source annotations under extras are archival, not the current text.
The current OmniVoice profile accepts voice-design tags; detailed natural-language
direction is retained but is not guaranteed to control its performance.

## Layered motion

`bin/console app:omc:motion xmas` renders shots with `motionLayers` using a fixed
SVG background, transparent SVG foreground and ordered pixel-position keyframes
(frame, x, y). Source layers remain separate and editable in the catalog; ffmpeg
composites a cached H.264 playback video and JPEG poster. This first adapter uses
rsvg-convert and ffmpeg, with no AI model. It is not native multitrack transform
playback in the browser yet. Static storyboard regeneration skips motion shots.
The reversing shot is 360 frames at 30 fps; impact occurs at frame 210 with the
sound cue on the same timeline frame. A brief recoil is followed by a hold.


The arrival brief calls for readable Route 422 shields and an LVC direction sign,
with the college frontage included in the separate Annville driving experiment.
These are authored story cues; verify actual sign placement against the route
before treating them as a geographically accurate reconstruction. Turkey Hill
and the fictional neighboring Kreider’s Auto share a visible forecourt in this
cut. The walk uses a transparent Jess layer and a procedural footstep WAV.
Recognition now follows the repair diagnosis, not the initial doorway greeting.
