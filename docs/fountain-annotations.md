# Fountain annotations (`rph-annotations/1`)

Fountain is the archival format: a readable screenplay that any Fountain app opens. OTIO is the playable form generated from it. As voices, sounds and images are generated, the OTIO's placeholders are swapped for real media; the Fountain does not change.

Machine-readable direction lives in Fountain **notes** (`[[...]]`). Fountain apps hide notes, so the printed screenplay stays clean. The vocabulary is in [`data/vocabulary/rph-annotations-1.json`](../data/vocabulary/rph-annotations-1.json). The first script written this way is [`data/originals/christmas-facing-west.fountain`](../data/originals/christmas-facing-west.fountain).

## What Fountain gives us, and what it doesn't

Fountain has no vocabulary for shots, sound, music or performance ([syntax](https://fountain.io/syntax/)). Its own shot example is a forced heading, `.SNIPER SCOPE POV`. We use its standard elements as far as they go:

| Fountain element | Our use |
| --- | --- |
| Title page key `Vocabulary:` | Declares `rph-annotations/1` |
| Section `#` | Acts or stages of the piece |
| Synopsis `=` | What a beat is for |
| Scene heading + scene number `#03#` | One beat; the scene number is its stable ID |
| Forced heading `.TITLE #12#` | A beat with no location (black, title card) |
| Action | Picture and sound, in prose |
| Centered text `>TEXT<` | On-screen cards |
| Parenthetical | Performance direction in natural language |
| Transition `SMASH CUT TO:` | Cuts that matter dramatically |
| Notes `[[...]]` | Everything machine-readable |
| Boneyard `/* */` | Production notes kept with the script |

## Note syntax

A note at the end of a line annotates that line's element. Attributes are `key=value`, separated by spaces. Lists use commas. Free text is quoted.

```
Mom, I can't do Christmas this year.[[id=01c to=LINDA emo=worried:2 act=placate pace=fast]]
```

A note on its own line that starts with `def` declares something the script refers to:

```
[[def mus:theme kind=score "Original piano motif, five rising notes."]]
[[def char:JESS "Late 20s. Speeds up when defensive, slows down when honest."]]
```

## Performance: natural language first

Acting has no formal standard, and nuance is what matters, so the **parenthetical is the primary direction**. It is written for an actor and passed verbatim to the agent or TTS engine. The keys below add consistent signals beside it; they never replace it.

| Key | Values | Standard |
| --- | --- | --- |
| `emo` | Emotion and intensity 1–3: `worried:2,amused:1` | [EmotionML everyday categories](https://www.w3.org/TR/emotion-voc/) (17 terms) plus 8 extensions |
| `pace` | `x-slow` `slow` `medium` `fast` `x-fast` | [SSML](https://www.w3.org/TR/speech-synthesis11/) prosody rate |
| `vol` | `x-soft` `soft` `medium` `loud` `x-loud` | SSML prosody volume |
| `pre`, `post` | Seconds of silence before/after the line | SSML break time |
| `ovl` | Seconds the line overlaps the previous one | — |
| `end` | `full` `trail` `cutoff` | — |
| `stress` | Words to stress: `stress=December` | — |
| `act` | The verb the character plays on the listener: `placate`, `needle`, `confide` | Actioning, as taught to actors; open list, lowercase verbs |
| `to` | Addressee | PSL speech events take an addressee |
| `fx` | `phone` `car-speaker` `pa` `radio` `none` | — |
| `say` | Quoted instruction that overrides the compiled one | — |
| `take` | The chosen audition take, written back after review | — |

EmotionML's everyday list lacks some emotions comedy needs, so the extensions are: embarrassed, guilty, hurt, incredulous, mischievous, nervous, surprised, wistful.

## Picture

| Key | Values |
| --- | --- |
| `shot` | PSL sizes `ECU` `CU` `MCU` `MS` `MLS` `LS` `ELS`, plus `TWO(A,B,size=MS)`, `OTS(from=A,to=B,size=MCU)`, `INSERT(thing)`, `POV(A)`, `BLACK` |
| `move` | `hold` `push-in` `pull-out` `pan-left` `pan-right` `tilt-up` `tilt-down`, optional `/slow` `/med` `/fast` |
| `img` | Slug(s) for the still; the action prose is its description and image prompt |
| `card` | `title` `lower-third` `credits`, on a centered-text line |
| `cut` | `hard` `black` `fade` `crossfade` |

Shot sizes and aliases follow [Prose Storyboard Language](production-and-shot-language.md), the formal shot grammar this project already references. A full PSL sentence can go in a note as free text when an alias is not enough.

## Sound and music

| Key | Values |
| --- | --- |
| `sfx` | One-shot effects, `category.name`: `impact.bumper-plow` |
| `amb` | Ambience beds with `:in` or `:out`: `amb=office-hvac:in` |
| `mus` | Declared cues with an action: `in` `out` `play` `hold` `drop` `swell` `duck` `sting` `stop` `resolve` |
| `bridge` | A sound carried across a cut: `sfx:vehicle.siren>mus:theme` |

Sound IDs are our own for now. The [Universal Category System](https://universalcategorysystem.com/) (public domain, version 8.2.1) is the industry list for sound-effect categories; its CatID list is in its Dropbox and has not been pulled in yet. Map `sfx` categories to UCS CatIDs when it is.

## Timing and identity

- `id` is the element's stable ID: the scene number plus a letter (`01c`). Keep IDs when reordering; generated media and takes are keyed to them.
- `at` and `dur` are the planned trailer time (`at=0:13 dur=7`). The edit may move them; the plan stays in the script.

## How it compiles

| Annotation | VoiceStudio | VRM stage | OTIO |
| --- | --- | --- | --- |
| Parenthetical | `instructions`, verbatim | `moodFor()` fallback | — |
| `emo`, `act`, `pace`, `vol`, `stress` | Appended to `instructions` as plain sentences | `emo` → preset expression | — |
| `pre`, `post`, `ovl` | — | — | Gaps and clip offsets on the dialogue track |
| `fx` | Post-processing (EQ) on the generated file | — | — |
| `shot`, `move`, `img` | — | Camera framing | Image clip with `metadata.survos.motion` |
| `card` | — | — | Clip with `metadata.survos.layout` |
| `sfx`, `amb`, `mus` | — | — | Clips on sound-effects, ambience and music tracks |

Mappings for VRM expressions and OTIO motion are in the vocabulary JSON under `compile`. The compiler from Fountain to OTIO is not written yet.

## Parser support

`FountainParser` strips notes and boneyard from element text, so notes are never spoken. It keeps notes on the element (`ParsedElement::$notes`), the scene (`ParsedScene::$notes`) or the script (`ParsedScript::$notes`, for standalone notes such as `def`). It reads scene numbers into `ParsedScene::$sourceId` and centered text as action. Notes are not yet stored in the database, so a database import keeps the text but not the annotations; the compiler should read the `.fountain` file.
