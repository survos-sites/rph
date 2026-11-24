<?php

declare(strict_types=1);
namespace App\Omc;

/** Fountain-like review export. Custom visual notes are not standard Fountain syntax. */
final class ReviewScreenplay
{
    public function render(array $review): string
    {
        $fps = $review['fps']; $events = []; $claimed = [];
        $clock = static fn(int $f): string => sprintf('%02d:%02d.%03d', intdiv($f,$fps*60), intdiv($f,$fps)%60, (int) round(($f%$fps)*1000/$fps));
        $cue = static function(array $s) use ($clock): string {
            return sprintf('[[VISUAL %s | %s | %s | %s]]', $clock($s['startFrame']), $s['name'] ?: 'Untitled picture', $s['description'] ?? 'Description missing', $s['image'] ?? $s['media']['url']);
        };
        foreach ($review['audio'] as $a) {
            if ($a['track'] !== 'Dialogue') continue;
            $text = $a['line']['text'] ?? $a['name'];
            $words = preg_split('/\s+/u', $text); $inserts = [];
            foreach ($review['shots'] as $s) {
                if ($s['startFrame'] <= $a['startFrame'] || $s['startFrame'] >= $a['endFrame']) continue;
                $position = max(1, min(count($words)-1, (int) round(count($words)*($s['startFrame']-$a['startFrame'])/($a['endFrame']-$a['startFrame']))));
                $inserts[$position][] = $cue($s).' [[WORD POSITION APPROXIMATE]]'; $claimed[$s['index']] = true;
            }
            $parts = [];
            foreach ($words as $i=>$word) { foreach ($inserts[$i] ?? [] as $note) $parts[]=$note; $parts[]=$word; }
            $speaker = explode(': ', $a['name'], 2)[0];
            $delivery = $a['line']['direction']['say'] ?? null;
            $events[] = ['frame'=>$a['startFrame'], 'order'=>1, 'text'=>$speaker.' [[LINE '.$a['elementId'].' '.$clock($a['startFrame']).'–'.$clock($a['endFrame'])."]]\n".($delivery ? '('.$delivery.")\n" : '').implode(' ', $parts)."\n"];
        }
        $lastScene = null;
        foreach ($review['shots'] as $s) {
            $heading = '';
            if ($s['scene'] && $s['scene']!==$lastScene) { $heading='## '.$s['scene']."\n\n"; $lastScene=$s['scene']; }
            if (isset($claimed[$s['index']])) continue;
            $events[] = ['frame'=>$s['startFrame'], 'order'=>0, 'text'=>$heading.$cue($s)."\n[[HOLD ".number_format($s['durationSeconds'],2).' seconds | source trim '.$s['sourceStartFrame'].' frames | shot '.$s['index']."]]\n"];
        }
        usort($events, fn($a,$b)=>[$a['frame'],$a['order']]<=>[$b['frame'],$b['order']]);
        return 'Title: '.$review['title']."\nDraft date: Current edit review\n\n/* GENERATED, READ-ONLY. Fountain-like custom directives, not a replacement source script.\nVISUAL times are exact edit times. Placement inside spoken text is an approximate reading aid, NOT word alignment.\nImage URLs are relative to https://rph.wip. Open /productions/".($review['slug'] ?? 'xmas')."/review for images, recipes and prompt history.\nDescriptions are authored intent, not independently verified visual content. */\n\n".implode("\n", array_column($events,'text'));
    }
}
