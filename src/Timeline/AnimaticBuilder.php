<?php
namespace App\Timeline;

use App\Entity\Script;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Shot list with graphics. OTIO owns cut, subtitle and speaking/rest timing. */
final readonly class AnimaticBuilder
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function build(Script $script, array $data): void
    {
        $cast = []; foreach ($script->characters as $role) { $cast[explode(':', $role->id)[1]] = $role; }
        $plan = ['wide','ots-eli','ticket','wide-sitting','ots-mara','ots-eli','closeup-mara','closeup-eli','ots-mara','ots-eli','wide','wide'];
        $lines = [];
        foreach ($script->scenes as $scene) foreach ($scene->elements as $line) $lines[$line->sequence] = $line;
        $pictures = $captions = $voices = $cues = $shots = []; $cursor = 0;
        $fs = new Filesystem();
        foreach ($data['cues'] as $i=>$cue) {
            $shot = $plan[$i];
            $line = $lines[$i+1] ?? null;
            $audio = $line?->speech;
            if ($audio && ($audio->text !== $line->text || !$audio->audioUrl)) $audio = null;
            $frames = $audio ? (int) ceil($audio->duration*30) + 12 : (int) ceil($cue['duration']*30);
            $lead = $audio ? (int) floor($audio->speechStart*30) : 6;
            $speakingFrames = $audio ? min($frames-$lead, (int) ceil($audio->speechDuration*30)) : $frames-18;
            $label = match($shot) {'wide'=>'Wide two-shot', 'wide-sitting'=>'Wide · Eli on the bench', 'ticket'=>'Insert · ticket in Eli’s hand', default=>str_replace(['ots-','closeup-'], ['Over shoulder · ','Close-up · '], $shot)};
            $paths = [];
            foreach ([false,true] as $speaking) {
                $body = preg_replace('/<svg /', '<svg x="0" y="0" width="1280" height="720" preserveAspectRatio="none" ', file_get_contents($this->dir.'/data/animatics/platform.svg'), 1);
                $place = function (string $who, string $pose, int $x, int $y, int $w, int $h) use ($cast,$cue,$speaking): string {
                    $key = $pose.'/'.$cue['expressions'][$who].'/'.($speaking && $cue['speaker']===$who?'speaking':'rest');
                    return preg_replace('/<svg /', sprintf('<svg x="%d" y="%d" width="%d" height="%d" ', $x,$y,$w,$h), file_get_contents($this->dir.'/public'.$cast[$who]->poseAssets[$key]), 1);
                };
                $body .= '<ellipse cx="355" cy="550" rx="57" ry="12" fill="#263247" opacity=".2"/><ellipse cx="925" cy="550" rx="57" ry="12" fill="#263247" opacity=".2"/>';
                $body .= match($shot) {
                    'wide'=>$place('mara','standing-right',200,210,310,470).$place('eli','standing-left',770,210,310,470),
                    'wide-sitting'=>$place('mara','standing-right',200,210,310,470).$place('eli','sitting-left',770,300,310,420),
                    'ots-eli'=>$place('eli','closeup-left',610,130,470,470).$place('mara','back',-190,220,760,1000),
                    'ots-mara'=>$place('mara','closeup-right',200,130,470,470).$place('eli','back',720,220,760,1000),
                    'closeup-mara'=>$place('mara','closeup-right',310,90,660,550),
                    'closeup-eli'=>$place('eli','closeup-left',310,90,660,550),
                    'ticket'=>'<path d="M950 720 850 390Q780 280 650 350L570 540 680 720" fill="#edc5a1" stroke="#29354b" stroke-width="8"/><g transform="translate(360 180) rotate(-9 250 140)"><rect width="540" height="290" rx="16" fill="#f9e6bb" stroke="#29354b" stroke-width="8"/><text x="35" y="60" font-family="sans-serif" font-size="26">NORTH PLATFORM · SINGLE</text><path d="M30 90h480" stroke="#29354b"/><text x="35" y="155" font-family="sans-serif" font-size="38">VALID: YESTERDAY</text><text x="35" y="220" font-family="sans-serif" font-size="24">LAST TRAIN · ONE PASSENGER</text></g>',
                };
                $title = htmlspecialchars(sprintf('%02d · %s', $i+1,$label), ENT_XML1);
                $body .= '<rect x="24" y="24" width="550" height="44" rx="8" fill="#202637"/><text x="42" y="54" fill="#ffe0aa" font-family="sans-serif" font-size="23">'.$title.'</text>';
                $path = '/media/generated/last-train/shot-'.($i+1).($speaking?'-speaking':'-rest').'.svg';
                $fs->dumpFile($this->dir.'/public'.$path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 720">'.$body.'</svg>'); $paths[$speaking?'speaking':'rest']=$path;
            }
            $meta = ['id'=>$cue['id'],'sceneId'=>'shot-'.($i+1),'sceneNumber'=>$i+1,'heading'=>$label,'type'=>'dialogue','speaker'=>$cast[$cue['speaker']]->name,'text'=>$cue['text'],'startFrame'=>$cursor,'durationFrames'=>$frames,'expressions'=>$cue['expressions'],'shot'=>$shot,'action'=>$cue['action'],'audio'=>$audio?->audioUrl,'audioDuration'=>$audio?->duration,'deliveryNotes'=>$audio?->deliveryNotes ?? []];
            $cues[]=$meta;
            // A short resting lead/tail makes dialogue onset and end visible, even when seeking.
            foreach ([['rest',$lead],['speaking',$speakingFrames],['rest',$frames-$lead-$speakingFrames]] as [$state,$length]) {
                if ($length <= 0) continue;
                $pictures[]=$this->clip('', 'asset:'.$paths[$state],$length,[], $meta+['mouth'=>$state]);
            }
            if ($audio) {
                $audioFrames = (int) ceil($audio->duration*30);
                $voices[] = $this->clip($meta['speaker'], 'asset:'.$audio->audioUrl, $audioFrames, [], $meta);
                $voices[] = $this->gap($frames-$audioFrames);
            } else { $voices[] = $this->gap($frames); }
            $captions[]=$this->clip('','caption:transparent',$frames,['caption'=>$meta['speaker'].': '.$cue['text'],'eyebrow'=>''],$meta);
            $shots[]=['id'=>$meta['sceneId'],'sequence'=>$i+1,'heading'=>$label,'startFrame'=>$cursor,'durationFrames'=>$frames,'image'=>$paths['rest'],'placeholder'=>true,'artwork'=>['placeholderDescription'=>$cue['action'],'imagePrompt'=>$data['setBrief']['imagePrompt'],'specUrl'=>'/scripts/last-train/animatic.otio']];
            $cursor += $frames;
        }
        $document=['OTIO_SCHEMA'=>'Timeline.1','name'=>$script->title.' · SVG shot list','global_start_time'=>null,'metadata'=>['survos'=>['version'=>1,'fps'=>30,'width'=>1280,'height'=>720],'rph'=>['script'=>$script->id,'mode'=>'animatic','scenes'=>$shots,'cues'=>$cues,'setBrief'=>$data['setBrief'],'renderer'=>'StickyFig']], 'tracks'=>['OTIO_SCHEMA'=>'Stack.1','name'=>'tracks','source_range'=>null,'metadata'=>[],'effects'=>[],'markers'=>[],'children'=>[$this->track('Shots',$pictures),$this->track('Subtitles',$captions),$this->track('Voices',$voices,'Audio')]]];
        $fs->dumpFile($this->dir.'/data/timelines/'.$script->id.'.otio',json_encode($document,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    private function clip(string $name,string $url,int $frames,array $survos,array $rph): array
    {
        return ['OTIO_SCHEMA'=>'Clip.2','name'=>$name,'source_range'=>['OTIO_SCHEMA'=>'TimeRange.1','start_time'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>0,'rate'=>30],'duration'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>$frames,'rate'=>30]],'effects'=>[],'markers'=>[],'metadata'=>['survos'=>$survos,'rph'=>$rph],'media_references'=>['DEFAULT_MEDIA'=>['OTIO_SCHEMA'=>'ExternalReference.1','target_url'=>$url,'metadata'=>[],'available_range'=>null]],'active_media_reference_key'=>'DEFAULT_MEDIA'];
    }
    private function gap(int $frames): array
    { return ['OTIO_SCHEMA'=>'Gap.1','name'=>'Pause','source_range'=>['OTIO_SCHEMA'=>'TimeRange.1','start_time'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>0,'rate'=>30],'duration'=>['OTIO_SCHEMA'=>'RationalTime.1','value'=>$frames,'rate'=>30]],'metadata'=>[],'effects'=>[],'markers'=>[]]; }
    private function track(string $name,array $children,string $kind = 'Video'): array
    { return ['OTIO_SCHEMA'=>'Track.1','name'=>$name,'kind'=>$kind,'source_range'=>null,'effects'=>[],'markers'=>[],'metadata'=>[],'children'=>$children]; }
}
