<?php

declare(strict_types=1);
namespace App\Omc;

/** A resolved, read-only review view. Edit placement is authoritative; catalog intent is labelled separately. */
final class EditReview
{
    public function build(Production $p): array
    {
        $edit = $p->edit(); $fps = $edit['fps']; $shots = []; $lines = []; $audio = []; $pictures = [];
        foreach ($p->all('Asset') as $a) {
            $s = Production::extension($a, 'rph.shot/1');
            if (isset($s['media'])) $shots[Production::key($s['media'])][] = ['entity'=>$a, 'shot'=>$s];
        }
        foreach ($p->all('NarrativeScene') as $scene) {
            $number = 0;
            foreach (Production::extension($scene, 'rph.scene/1')['elements'] ?? [] as $line) {
                $reference = $line['type']==='dialogue' ? ltrim((string)$scene['sceneNumber'],'0').'.'.(++$number) : null;
                $lines[$line['id']] = $line + ['audioReference'=>$reference, 'scene'=>$scene['name'], 'sceneId'=>Production::key($scene)];
            }
        }
        foreach ($edit['tracks'] as $track) foreach ($track['clips'] as $clip) {
            if ($track['kind'] === 'Video') $pictures[] = $clip + ['track'=>$track['name']];
            if ($track['kind'] !== 'Audio') continue;
            $id = $clip['extras']['metadata']['rph']['element'] ?? null;
            $line = $id === null ? null : ($lines[$id] ?? null);
            $audio[] = ['track'=>$track['name'], 'startFrame'=>$clip['startFrame'], 'endFrame'=>$clip['startFrame']+$clip['durationFrames'], 'sourceStartFrame'=>$clip['sourceStartFrame'], 'name'=>$clip['name'], 'elementId'=>$id, 'audioReference'=>$line['audioReference'] ?? null, 'line'=>$line, 'media'=>$p->media($clip['asset']), 'presentation'=>$clip['presentation']];
        }
        // A rendered video may contain several authored cuts. Expand only the review, never the edit.
        $visuals = [];
        foreach ($pictures as $clip) {
            $definitions = $shots[Production::key($clip['asset'])] ?? [];
            $changes = count($definitions)===1 ? ($definitions[0]['shot']['sourceMedia']['visualChanges'] ?? []) : [];
            if (!$changes) { $visuals[] = $clip; continue; }
            foreach ($changes as $i=>$change) {
                $endOffset = $changes[$i+1]['offsetFrame'] ?? $clip['durationFrames'];
                if ($change['offsetFrame'] < 0 || $endOffset <= $change['offsetFrame'] || $endOffset > $clip['durationFrames']) throw new \InvalidArgumentException('Invalid review visual boundary.');
                $visual = $clip;
                $visual['startFrame'] += $change['offsetFrame'];
                $visual['sourceStartFrame'] += $change['offsetFrame'];
                $visual['durationFrames'] = $endOffset-$change['offsetFrame'];
                $visual['reviewVisual'] = $change;
                $visuals[] = $visual;
            }
        }
        $pictures = $visuals;
        usort($pictures, fn($a,$b)=>$a['startFrame']<=>$b['startFrame']);
        $rows = []; $previous = null;
        foreach ($pictures as $i=>$clip) {
            $start = $clip['startFrame']; $end = $start+$clip['durationFrames']; $media = $p->media($clip['asset']);
            $candidates = $shots[Production::key($clip['asset'])] ?? [];
            $match = array_values(array_filter($candidates, fn($c)=>($c['shot']['startFrame'] ?? null)===$start))[0] ?? (count($candidates)===1 ? $candidates[0] : null);
            $s = $match['shot'] ?? []; $scene = isset($s['scene']) ? $p->get($s['scene']) : null;
            $overlaps = array_values(array_filter($audio, fn($a)=>$a['startFrame']<$end && $a['endFrame']>$start));
            $flags = [];
            if (!$match) $flags[] = 'No unambiguous shot definition for this edit clip.';
            if (!isset($clip['reviewVisual']) && isset($s['durationFrames']) && $s['durationFrames']!==$clip['durationFrames']) $flags[] = 'Shot duration disagrees with edit duration.';
            if ($clip['durationFrames'] < $fps) $flags[] = 'Shot shorter than one second: inspect readability.';
            if ($previous && $previous['endFrame']!==$start) $flags[] = 'Picture gap or overlap at incoming cut.';
            foreach ($overlaps as $a) if ($a['track']==='Dialogue') {
                if ($a['startFrame']<$start && $a['endFrame']>$start) $flags[] = 'Incoming cut occurs during dialogue (may be intentional).';
                if ($scene && isset($a['line']['sceneId']) && $a['line']['sceneId']!==Production::key($scene)) $flags[] = 'Dialogue and picture belong to different script scenes (check audio bridge).';
                if (isset($a['media']['speech']['text'],$a['line']['text']) && $a['media']['speech']['text']!==$a['line']['text']) $flags[] = 'Recorded text differs from current script.';
            }
            $renderer = $media['renderer'] ?? null;
            $recipe = $s['motionLayers'] ?? $s['sourceMedia'] ?? $s['storyboard'] ?? null;
            $flags[] = 'Visual description is authored intent, not independently verified image content.';
            $row = ['index'=>$i+1, 'imageReference'=>'Image '.($i+1), 'name'=>$clip['reviewVisual']['name'] ?? $match['entity']['name'] ?? $clip['name'], 'scene'=>$scene['name'] ?? null, 'startFrame'=>$start, 'endFrame'=>$end, 'durationFrames'=>$clip['durationFrames'], 'startSeconds'=>$start/$fps, 'endSeconds'=>$end/$fps, 'durationSeconds'=>$clip['durationFrames']/$fps, 'sourceStartFrame'=>$clip['sourceStartFrame'], 'mediaReference'=>$clip['asset'], 'media'=>$media, 'image'=>$clip['reviewVisual']['image'] ?? ($media['kind']==='video' ? ($media['poster'] ?? null) : $media['url']), 'description'=>$clip['reviewVisual']['name'] ?? $s['storyboard']['description'] ?? $match['entity']['description'] ?? null, 'framing'=>$s['framing'] ?? null, 'presentation'=>$clip['presentation'], 'generation'=>['renderer'=>$renderer, 'currentRecipe'=>$recipe, 'currentPrompt'=>null, 'promptStatus'=>'No verified exact prompt recorded for the selected asset; procedural renders use the recipe instead.', 'authoredPromptTemplate'=>$s['promptTemplate'] ?? null, 'legacyGenerationSettings'=>$s['generation'] ?? null, 'provenance'=>$media['extras'] ?? null], 'audio'=>$overlaps, 'incomingTransition'=>['previousShot'=>$previous['name'] ?? null, 'previousScene'=>$previous['scene'] ?? null, 'type'=>($clip['presentation']['fadeInSeconds'] ?? 0)>0 ? 'fade-in' : 'cut', 'flags'=>array_values(array_unique($flags))]];
            $beats = []; $cursor = $start;
            $spoken = array_values(array_filter($overlaps, fn($a)=>$a['track']==='Dialogue'));
            usort($spoken, fn($a,$b)=>$a['startFrame']<=>$b['startFrame']);
            $quiet = static function(int $from, int $to) use ($overlaps, $fps): array {
                $sounds = array_column(array_filter($overlaps, fn($a)=>$a['track']!=='Dialogue' && $a['startFrame']<$to && $a['endFrame']>$from), 'name');
                return ['kind'=>'quiet', 'seconds'=>($to-$from)/$fps, 'sound'=>$sounds ? 'Sound bed: '.implode('; ',array_unique($sounds)).'.' : 'No audio is scheduled.'];
            };
            foreach ($spoken as $a) {
                $from = max($start,$a['startFrame']);
                if ($from-$cursor >= $fps/2) $beats[] = $quiet($cursor,$from);
                $beats[] = ['kind'=>'dialogue','audioReference'=>$a['audioReference'], 'audioUrl'=>$a['media']['url'], 'elementId'=>$a['elementId'], 'speaker'=>explode(': ', $a['name'],2)[0], 'text'=>$a['line']['text'] ?? $a['name'], 'delivery'=>$a['line']['direction']['say'] ?? null, 'continues'=>$a['startFrame']<$start];
                $cursor = max($cursor,min($end,$a['endFrame']));
            }
            if ($end-$cursor >= $fps/2) $beats[] = $quiet($cursor,$end);
            $row['readingBeats'] = $beats;
            $rows[] = $row; $previous = $row;
        }
        return ['schema'=>'rph.edit-review/1', 'slug'=>$p->slug, 'title'=>$p->project()['name'], 'fps'=>$fps, 'timingConvention'=>'Integer frames; end exclusive. Edit timings are authoritative. Audio lists include every overlapping clip, not just clips starting within a shot.', 'reviewInstructions'=>'Review pacing, visual continuity, dialogue bridges and story causality. Descriptions and templates are intent, not visual evidence. Open the linked images; for video inspect source cuts or frames, not only its poster. Flags are review candidates, not automatic errors. Do not change the edit while reviewing.', 'shots'=>$rows, 'script'=>$p->all('NarrativeScene'), 'characters'=>$p->all('Character'), 'catalog'=>['props'=>$p->all('NarrativeObject'), 'locations'=>$p->all('NarrativeLocation')], 'audio'=>$audio];
    }
}
