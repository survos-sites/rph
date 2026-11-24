# The Last Train proof of concept

The Last Train is an original AI-authored demonstration scene, designated CC0 in its source JSON, not a downloaded screenplay. Its two characters, station backdrop and twelve shots exercise RPH's character, line performance and OTIO pipeline.

## Reproduce locally

Start VoiceStudio at `VOICESTUDIO_URL`. The cast specifies the locally cached OmniVoice model, with 32 inference steps (its documented quality preset), distinct design tags and fixed seeds. There is no paid API call or fallback to macOS say/KittenTTS for this cast.

```sh
bin/console doctrine:migrations:migrate -n
bin/console app:last-train --sync
bin/console app:voices:queue last-train
bin/console messenger:consume line_voice.generate --limit=12
```

Alternatively run `bin/console survos:super --no-tui` for the configured character and line workers. `app:voices:queue --sync` runs the same workflow synchronously; `--force` creates new performance requests. Subsequent assembly without pose regeneration uses `app:last-train --skip-poses`.

Open `/scripts/last-train/animatic`. Character detail pages expose general voice descriptions, design tags, individual delivery directions, pace, whispering and per-line playback/regeneration.

## Data and workflow

Character age and gender are shared facts passed to the pose renderer and used as voice defaults. Appearance contains skin, hair, face shape, glasses and current clothing color. Voice settings can explicitly override defaults. Future outfits should be reusable wardrobe assets selected for a scene/shot, separate from character identity; an outfit entity is not implemented yet.

`ScriptElement` holds dialogue, annotations, delivery direction and its current `LineSpeech` reference. A `LineSpeech` freezes the text, effective voice specification and directions. Persisting it and flushing automatically dispatches `pending -> generate -> ready` through the native workflow attributes and the extras/messenger postFlush hook, to `line_voice.generate`. This is a dedicated transport, not the generic async queue. Messenger handles retries/failure transport. Failed requests remain pending on the entity; inspect the failure transport or create a new request to retry.

Each successful generation saves an MP3, measures its duration and speech boundaries, and refreshes the animatic. The OTIO voice, subtitle and image tracks have matching durations. Each line gets a 0.4-second pause after its measured audio. Mouth changes follow detected onset/end, not phoneme lip-sync or every internal pause. Missing audio temporarily uses estimated reading durations. Old queued requests cannot replace the active request.

OmniVoice supports gender, age, pitch, accent and whisper tags; speed is separate. The adapter uses comma-separated tags so the engine does not accidentally discard the final tag. Free-form emotion directions are retained, but this model's closed tag vocabulary does not include angry/happy. Fixed seeds and design tags are a prototype voice identity; a locked reference recording would provide stronger consistency.

## Storage and production direction

The current proof of concept writes generated media beneath ignored `public/media/` paths. Database rows retain references and measurements; audio binaries are not database columns or Git assets.

The agreed next storage step is a dedicated RPH S3 bucket for audio and stills, with one storage interface used by local and production workers. Use content-addressed object keys, retain model/prompt provenance, and resolve stable asset IDs to public/CDN or signed URLs at playback. OTIO should not embed expiring signed URLs. A production worker should use a configured speech-service endpoint (or run where the local engine is available), then upload to the same bucket. Bucket provisioning, S3 uploads and remote URL resolution are not yet implemented.
