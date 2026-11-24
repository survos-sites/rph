# Pluggable shot rendering: a minimal direction

Status: proposal, not a new rendering framework. Keep improving the cave proof of concept before introducing more infrastructure.

## Editable source

RPH stores a versioned JSON description of a shot. Images are optional outputs, not the only representation of that shot. Keep dialogue, audio timing and camera cuts independent. OTIO assembles those tracks; it does not execute JavaScript. Our browser player may resolve an asset reference to a live renderer, while conventional OTIO consumers receive a baked still or clip.

A shot can initially be just:

```json
{
  "schemaVersion": 1,
  "id": "sintel-cave-bowl",
  "description": "Close-up of bowl reflecting Sintel's face",
  "referenceStill": "asset:sintel-cave/shot-9",
  "renderer": "reference"
}
```

That description is enough until we implement the composition. Do not invent reflection simulation, model conversion, or hand constraints just to fill a placeholder. Keep the original still available as a reference and label any descriptive placeholder clearly.

## Renderers

- **Reference still:** already working. Original source shots and audio provide the comparison edit.
- **StickyFig:** the first live preview candidate. Render stored JSON to SVG in the browser, updating pose, expression and mouth from the current frame. The pinned library contains pose/expression controls and SVG/CSS animation helpers. RPH currently invokes it through Node and serves pre-rendered SVGs; live JSON playback is still to be implemented. Its CommonJS modules need browser packaging or an adapter.
- **Blender:** a future queued renderer using a `.blend` template and a small Python adapter. Set supported rig controls, wardrobe, camera and lights from the same shot intent, render an image or clip, and return an asset reference. No HTTP service is necessary initially: an existing workflow consumer can launch headless Blender.
- **WebGL:** an optional future browser adapter, for example Three.js loading GLB/glTF props. A 3D bowl can have real viewpoint changes; rendering a reflection of a 2D character still requires an explicit technique. A WebGL prop does not automatically solve reflections or compositing.

Do not require every renderer to support everything. Start with one adapter and a descriptive or reference-image fallback. Add a capability check only when a real unsupported request requires it. Reuse `PoseRendererInterface` for character snapshots; introduce a separate shot-level interface only when live whole-shot composition needs it.

## Shared character and prop data

Character identity, age, face shape, skin and voice belong to the character. Pose and expression vary within a shot. Outfits can later group clothing and accessories selected for a scene; they should not duplicate the character. Renderer-specific rig names or asset paths belong to adapter mappings.

A prop has a stable identity and may have SVG, GLB/glTF and Blender representations. These representations need not exist immediately or be automatically interchangeable. For the cave bowl, a description and the original still are sufficient today.

Prefer existing libraries before drawing or modeling props. Candidates checked during discussion:

- [Kenney Food Kit](https://kenney.nl/assets/food-kit): CC0 food/kitchen models; a good first library for simple 3D props.
- [Game-icons](https://game-icons.net/about.html): SVG imagery, generally CC BY 3.0; retain author and per-asset licence. Useful for flat placeholders, not arbitrary camera views.
- [Poly Haven](https://polyhaven.com/models): 3D model library; asset licences and API usage terms are separate. Evaluate specific assets when needed.

No asset catalogue service, Unity integration, or new model downloads are needed for this proposal.

## Timing and execution

A live renderer should behave as `render(shot, frame, fps)`: seeking to a frame must give the same result as playing to it. Drive mouth changes, expressions and any motion from the timeline clock, rather than free-running CSS animations. Preview can be immediate; expensive exports use our native Symfony workflow attributes plus extras/messenger, `next` and `async: true`, and dedicated transition queues. Keep transport wiring consistent with the installed bundles.

Save the effective specification and renderer version with each generated artifact. Later, local and production workers should write through the same storage interface to a dedicated RPH S3 bucket. OTIO and JSON use stable asset references, resolved to delivery URLs at playback; do not bake expiring signed URLs into the edit. S3 provisioning and this storage abstraction are not implemented yet.

An LLM can propose shot descriptions and eventually validated JSON using known characters, props and supported controls. A trusted adapter translates that JSON into renderer operations. We do not need unrestricted LLM-generated Python to test the idea.

## Agreed implementation order

1. **Stick figures:** recreate the cave shot list with identifiable characters, facing and seated poses, expressions and a simple backdrop. Keep the original stills and audio as the reference. Descriptive placeholders are sufficient for unresolved shots, including “Close-up of bowl reflecting Sintel’s face.”
2. **Our generated voices:** use character voice definitions plus line delivery notes through the existing line-level workflow and dedicated queues. Retain the original performances for comparison. Measure new recordings and explicitly create a retimed version; do not overwrite the original source timing or squeeze speech into old durations.
3. **Higher-resolution pictures:** replace selected placeholder pictures with better artwork while preserving character, outfit and set continuity. Blender is a candidate, not a prerequisite. Reuse the established shot descriptions and compositions.

Add optional Ken Burns framing to still-based shots: a restrained push-in, pull-back or pan with explicit start/end framing. Keep the subject and caption area legible, avoid moving every shot, and retain locked-off shots for contrast. This provides visual movement without making lip animation a dependency.

Store motion on the shot, separately from the image and dialogue. Evaluate it from normalized shot time so seeking is deterministic. Use the existing OTIO/player motion support where possible; no new animation system is needed. Retiming a generated-voice version should preserve framing endpoints while adjusting motion duration to the new shot length. Keep lip animation optional and independently replaceable.

References: [Blender headless rendering](https://docs.blender.org/manual/en/latest/advanced/command_line/render.html), [Blender Python module](https://docs.blender.org/api/main/info_advanced_blender_as_bpy.html), [Flamenco](https://flamenco.blender.org/faq/) (future render-farm option, not required), and the pinned StickyFig source in `tools/sticky-fig/`.
