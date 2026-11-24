<?php
namespace App\Timeline;

use App\Entity\Script;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Keep source cuts, dialogue and a continuous original soundtrack on independent tracks. */
final readonly class SourceSceneBuilder
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function build(Script $script): array
    {
        $data=$script->projectMetadata; $fps=$data['source']['fps'];
        $total=(int)round(($data['source']['end']-$data['source']['start'])*$fps);
        $pictures=$captions=$shots=$cues=[];
        foreach($data['shots'] as $i=>$shot) {
            $name=$data['source']['shots'][$i]['heading'] ?? 'Shot '.($i+1);
            $description=$data['source']['shots'][$i]['description'] ?? 'Original still from this camera shot.';
            $shots[]=$shot+['id'=>'shot-'.($i+1),'sequence'=>$i+1,'heading'=>$name,'placeholder'=>false,'artwork'=>['placeholderDescription'=>$description,'imagePrompt'=>$description.' Cave shelter lit by a small fire. Sintel and the shaman face each other, seated; preserve this source shot’s camera angle.','specUrl'=>'/scripts/'.$script->id.'/animatic.otio']];
            $pictures[]=$this->clip('', 'asset:'.$shot['image'],$shot['durationFrames'],$fps);
        }
        $cursor=0;
        foreach($data['cues'] as $cue) {
            if($cue['startFrame']>$cursor) $captions[]=$this->gap($cue['startFrame']-$cursor,$fps);
            $shot=$shots[0];foreach($shots as $candidate) if($candidate['startFrame']<=$cue['startFrame']) $shot=$candidate;
            $meta=$cue+['type'=>'dialogue','sceneId'=>$shot['id'],'sceneNumber'=>$shot['sequence'],'heading'=>$shot['heading']];
            $cues[]=$meta;
            $captions[]=$this->clip('','caption:transparent',$cue['durationFrames'],$fps,['caption'=>$cue['speaker'].': '.$cue['text']],$meta);
            $cursor=$cue['startFrame']+$cue['durationFrames'];
        }
        if($cursor<$total) $captions[]=$this->gap($total-$cursor,$fps);
        $audio=$this->clip('Original performances and ambience','asset:'.$data['audio'],$total,$fps,[],['sourceStart'=>$data['source']['start'],'credit'=>$data['source']['attribution']]);
        $doc=['OTIO_SCHEMA'=>'Timeline.1','name'=>$script->title,'global_start_time'=>null,'metadata'=>['survos'=>['version'=>1,'fps'=>$fps,'width'=>1280,'height'=>720],'rph'=>['mode'=>'source-shots','script'=>$script->id,'scenes'=>$shots,'cues'=>$cues,'source'=>$data['source']]],'tracks'=>['OTIO_SCHEMA'=>'Stack.1','name'=>'tracks','source_range'=>null,'metadata'=>[],'effects'=>[],'markers'=>[],'children'=>[$this->track('Original camera cuts',$pictures,'Video'),$this->track('Timed dialogue',$captions,'Video'),$this->track('Original voices and ambience',[$audio],'Audio')]]];
        (new Filesystem())->dumpFile($this->dir.'/data/timelines/'.$script->id.'.otio',json_encode($doc,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        return $doc;
    }
    private function range(int $frames,int $fps): array { return ['OTIO_SCHEMA'=>'TimeRange.1','start_time'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>0,'rate'=>$fps],'duration'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>$frames,'rate'=>$fps]]; }
    private function clip(string $name,string $url,int $frames,int $fps,array $survos=[],array $rph=[]): array { return ['OTIO_SCHEMA'=>'Clip.2','name'=>$name,'source_range'=>$this->range($frames,$fps),'effects'=>[],'markers'=>[],'metadata'=>['survos'=>$survos,'rph'=>$rph],'media_references'=>['DEFAULT_MEDIA'=>['OTIO_SCHEMA'=>'ExternalReference.1','target_url'=>$url,'metadata'=>[],'available_range'=>null]],'active_media_reference_key'=>'DEFAULT_MEDIA']; }
    private function gap(int $frames,int $fps): array { return ['OTIO_SCHEMA'=>'Gap.1','name'=>'Pause','source_range'=>$this->range($frames,$fps),'metadata'=>[],'effects'=>[],'markers'=>[]]; }
    private function track(string $name,array $children,string $kind): array { return ['OTIO_SCHEMA'=>'Track.1','name'=>$name,'kind'=>$kind,'source_range'=>null,'metadata'=>[],'effects'=>[],'markers'=>[],'children'=>$children]; }
}
