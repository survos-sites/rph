# Sintel: cave conversation source edit

Run `bin/console app:sintel-cave` to download the publisher-linked 720p movie and the Xiph English subtitles using fetch-bundle, import the twelve subtitle blocks into RPH, extract source-cut stills and the original audio, and write `data/timelines/sintel-cave.otio`.

Browse `/scripts/sintel-cave/animatic`. The first pass intentionally uses original film stills, before replacing those pictures with stick-figure compositions. No speech is synthesized and no character pose jobs are dispatched by this import.

The selected interval is 106.5–155.833333 seconds at 24 fps (49⅓ seconds). It begins inside the shelter with the blade/fire insert and continues into the opening city flashback, excluding the opening fight. Frame-difference cut detection at threshold 0.06 identifies eleven shots; these were visually reviewed and named in `data/sources/sintel-cave.json`. Captions keep their own timing and gaps, independently of camera cuts. Two additional visual beats at 40 and 44 seconds represent the camera leaving Sintel and the city emerging from darkness. These are editorial still changes within a camera move/fade, not hard cuts.

The voice track is one continuous 192 kbps stereo MP3 downmix of the film’s original 5.1 audio. This preserves performances, pauses and ambience, but is not an isolated dialogue stem. The original video, excerpt and generated stills/audio are ignored by Git. Source URLs, attribution, source-video SHA-256 and timing are retained in the script’s project metadata; the source manifest is versioned.

Source: © Blender Foundation | durian.blender.org, CC BY 3.0. Screenplay: Esther Wouda. Performances: Halina Reijn (Sintel), Thom Hoffman (Shaman). This adaptation extracts still frames, crops the scene duration, downmixes audio and lays out captions. See the linked publisher licence in the source manifest. The Wikisource page discussed earlier is a film transcript, not the production screenplay.

## Timing correction

The downloaded SRT is a starting reference, not final synchronization. Per-cue overrides in the source manifest now align captions to local word transcription and centre-channel speech boundaries. Original SRT times remain in each cue as `subtitleStart`/`subtitleEnd`; aligned source times drive the OTIO and database annotations. Caption gaps clear the active transcript row. Endpoints are rounded before subtracting so frame durations do not accumulate rounding drift.

Correction to the initial visual description: the source film fades from Sintel’s close-up into a city flashback before the final voice-over finishes. The animatic now changes to the dark shelter and then the city instead of holding Sintel’s face through those final lines. It represents the fade with stills, rather than reproducing the continuous movement. Hard-cut positions were rechecked at a lower detection threshold; the additional detections were motion/fade events, not missing reverse-angle cuts.
