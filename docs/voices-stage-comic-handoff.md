# Voices, 3D stage and comic: handoff (2026-11-23)

## Done

- **VoiceStudio voices** (commit 291ccf1). `RPH_VOICE_ENGINE` selects `say` (default) or `voicestudio`, the local [VoiceStudio](https://voicestudio.sh) app. rph calls its OpenAI-compatible `POST /v1/audio/speech` through Symfony AI's generic platform (`app.platform.voicestudio`, built by `src/Voice/Platform/VoiceStudioPlatform.php`); only the `/health` check uses HttpClient directly. Code is in `src/Voice/`.
  - A cast profile in `data/cast/<script>.json` may carry `"voicestudio": {"voice", "model", "instructions"}`.
  - Cache keys include the engine, model, voice and instructions. `say` keys are unchanged.
  - `app:voices <script> [--force]` generates the audio.
  - Settings: `VOICESTUDIO_URL` (default `http://127.0.0.1:3900`) and `VOICESTUDIO_MODEL` (default `kittentts`). Local `.env.local` sets `RPH_VOICE_ENGINE=voicestudio`.
  - `bob-and-sandy` and `the-wizard-of-oz` have KittenTTS preset voices (`expr-voice-{2..5}-{m,f}`).
- **3D stage prototype** (commit dc68bdc) at `/scripts/{scriptId}/scenes/{sceneId}/stage`, linked from the scene player as "3D stage".
  - Uses three-vrm with sample VRM 1.0 models. Run `tools/download-vrm-samples.sh` first; the models are pinned, verified and kept out of git.
  - Characters blink and breathe, and listeners look at the speaker. The camera frames the speaker.
  - The mouth follows the audio's loudness through a WebAudio analyser.
  - The parenthetical before a line picks a preset expression (`moodFor()` in `assets/controllers/vrm_stage_controller.js`).
  - The cast profile `"vrm": {"model", "tint"}` chooses the model.

## Waiting on

- **Upstream symfony/ai#3058** (text to speech in the Generic bridge). rph requires it from the fork `tacman/ai-generic-platform` (branch `generic-tts`, a `vcs` repository in composer.json). Once it's merged and released, drop the repository and require the tagged `symfony/ai-generic-platform`.

## Next

### Voices
- Pass Fountain parentheticals as VoiceStudio `instructions` so the delivery follows the script, the same signal the stage uses for faces. Test this with OmniVoice; KittenTTS probably ignores instructions.
- Use VoiceStudio's `/v1/audio/transcriptions` (word timestamps) for real lip-sync timing, and later for rehearsal mode: the actor speaks a line and rph checks it.
- Production: VoiceStudio and `say` are local Mac tools, so audio must be pre-generated and shipped or stored for a Linux deploy.

### 3D stage
- Give each character its own VRM, for example made in VRoid Studio. Right now Tin Man, Scarecrow and Lion share Seed-san, tinted, mechanical back-arm included.
- Toto (`nonSpeaking`) has no model.
- Add body animation, either Mixamo clips retargeted (three-vrm has an example) or MediaPipe and Kalidokit webcam capture. Add simple gestures per parenthetical or action.
- Use visemes (`aa`/`ih`/`ou`/`ee`/`oh`) from timestamps instead of loudness only.
- Action text covers much of the frame in the subtitle box; it needs a separate direction style.
- The models are about 22 MB, so first load is slow. Consider smaller models.
- Tests: none yet for the stage JS; `moodFor()` is exported for that.

### Comic with voice-over (no video rendering)
- Use `survos/otio-bundle` (`~/sites/mono/bu/otio-bundle`): an image track for panels (Ken Burns motion), an audio track for the cached voice lines, and captions. It still needs a **speech-balloon layout**, since it currently has only lower-third, title and credits.
- Character expression sheets: DiceBear SVG for a quick start; later, generated sheets made with ComfyUI and Flux Kontext or Qwen-Image-Edit, cached per character and expression like the voices.
- First target: a **non-song scene from Talented Clementine**. The songs exist in the KPA archive for later; they're out of scope for now.

### Better scripts (short, emotional, public domain in the US)
- Candidates: *Trifles* (Glaspell, 1916), *The Proposal* and *The Bear* (Chekhov, Julius West translations), *Riders to the Sea* (Synge), *The Rising of the Moon* (Lady Gregory), *The Stronger* (Strindberg), *Before Breakfast* and *Ile* (O'Neill), *Suppressed Desires* (Glaspell and Cook).
- Start by converting *Trifles* and *The Proposal* to Fountain, with provenance manifests like `data/sources/*.json`.
- Single scenes from Bard (for example *Macbeth* 1.7, the *Romeo and Juliet* balcony scene) instead of whole plays. This covers the README's "Bard integration" item.
- An open movie released as scenes for experimentation was found by Tac (title not recorded here). Check its licence and what assets it includes before using it.
- Big Fish is © Columbia Pictures (the official Fountain sample). It's fine for parser tests but not for a public demo.
