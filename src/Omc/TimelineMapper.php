<?php

declare(strict_types=1);

namespace App\Omc;

/** rph.edit/1 -> OTIO. Source media identity stays in OMC; timing is integer frames. */
final readonly class TimelineMapper
{
    public function map(Production $production): array
    {
        $edit = $production->edit();
        $fps = $edit['fps'];
        if (!is_int($fps) || $fps <= 0) throw new \InvalidArgumentException('Edit fps must be a positive integer.');
        $lines=[];
        foreach($production->all('NarrativeScene') as $scene) foreach(Production::extension($scene,'rph.scene/1')['elements'] ?? [] as $line) $lines[$line['id']]=$line;
        $tracks = [];
        foreach ($edit['tracks'] as $track) {
            $children = []; $cursor = 0;
            foreach ($track['clips'] as $clip) {
                foreach (['startFrame','durationFrames','sourceStartFrame'] as $field) {
                    if (!is_int($clip[$field]) || $clip[$field] < 0) throw new \InvalidArgumentException('Invalid edit frame: '.$field);
                }
                if ($clip['durationFrames'] === 0 || $clip['startFrame'] < $cursor) throw new \InvalidArgumentException('Empty or overlapping clips in '.$track['name']);
                if ($clip['startFrame'] > $cursor) $children[] = $this->gap($clip['startFrame']-$cursor,$fps);
                $media = $production->media($clip['asset']);
                if (!isset($media['url'],$media['kind']) || !in_array($media['kind'],['image','audio','video'],true)) throw new \InvalidArgumentException('Edit references an asset without playable media.');
                $metadata = $clip['extras']['metadata'] ?? [];
                $metadata['survos'] = $clip['presentation'];
                $line=$lines[$metadata['rph']['element'] ?? ''] ?? null;
                if (($line['direction']['audioStatus'] ?? $line['delivery']['audioStatus'] ?? null)==='stale' && $media['kind']==='audio') $metadata['survos']['gain']=0;
                $metadata['omc'] = $clip['asset'];
                $children[] = ['OTIO_SCHEMA'=>'Clip.2','name'=>$clip['name'],'source_range'=>$this->range($clip['sourceStartFrame'],$clip['durationFrames'],$fps),'effects'=>[],'markers'=>[],'metadata'=>$metadata,
                    'media_references'=>['DEFAULT_MEDIA'=>['OTIO_SCHEMA'=>'ExternalReference.1','target_url'=>$media['url'],'metadata'=>['omc'=>$clip['asset'],'survos'=>['kind'=>$media['kind']]],'available_range'=>null]],'active_media_reference_key'=>'DEFAULT_MEDIA'];
                $cursor = $clip['startFrame']+$clip['durationFrames'];
            }
            if ($cursor > $track['durationFrames']) throw new \InvalidArgumentException('Track ends before its last clip.');
            if ($cursor < $track['durationFrames']) $children[]=$this->gap($track['durationFrames']-$cursor,$fps);
            $tracks[]=['OTIO_SCHEMA'=>'Track.1','name'=>$track['name'],'kind'=>$track['kind'],'source_range'=>null,'effects'=>[],'markers'=>[],'metadata'=>[],'children'=>$children];
        }
        return ['OTIO_SCHEMA'=>'Timeline.1','name'=>$production->project()['name'],'global_start_time'=>null,
            'metadata'=>['survos'=>$edit['presentation'],'omc'=>['project'=>$production->project()['identifier'],'mapping'=>'rph.edit/1']],
            'tracks'=>['OTIO_SCHEMA'=>'Stack.1','name'=>'tracks','source_range'=>null,'effects'=>[],'markers'=>[],'metadata'=>[],'children'=>$tracks]];
    }
    public function resolve(Production $production, string $url): array
    {
        foreach ($production->all('Asset') as $asset) {
            $media=Production::extension($asset,'rph.media/1');
            if (($media['url'] ?? null) === $url) return ['id'=>$url,'url'=>$url,'kind'=>$media['kind'],'credit'=>$media['credit'] ?? ''];
        }
        throw new \InvalidArgumentException('Unknown OMC media URL.');
    }
    private function range(int $start,int $duration,int $fps): array
    {
        return ['OTIO_SCHEMA'=>'TimeRange.1','start_time'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>$start,'rate'=>$fps],'duration'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>$duration,'rate'=>$fps]];
    }
    private function gap(int $duration,int $fps): array { return ['OTIO_SCHEMA'=>'Gap.1','name'=>'','source_range'=>$this->range(0,$duration,$fps),'effects'=>[],'markers'=>[],'metadata'=>[]]; }
}
