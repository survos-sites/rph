<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production as P;
final readonly class WorkQueries
{
    public function __construct(private WorkCatalog $catalog) {}
    public function works(): array { return $this->catalog->works(); }
    public function scenes(string $work): array
    {
        $entities=$this->catalog->entities($work);$scenes=[];$mediaScenes=[];$edit=[];
        foreach ($entities as $e) {
            if ($e['entityType']==='NarrativeScene') {
                $v=P::extension($e,'rph.scene/1');
                $scenes[P::key($e)]=['id'=>$e['identifier'],'number'=>$e['sceneNumber'],'heading'=>$e['name'],'dialogueLines'=>count(array_filter($v['elements']??[],fn($l)=>$l['type']==='dialogue')),'pictureFrames'=>0];
            }
            $s=P::extension($e,'rph.shot/1');if(isset($s['media'],$s['scene'])) $mediaScenes[P::key($s['media'])][P::key($s['scene'])]=true;
            if ($v=P::extension($e,'rph.edit/1')) $edit=$v;
        }
        $unassigned=0;
        foreach ($edit['tracks']??[] as $t) if($t['name']==='Pictures') foreach($t['clips'] as $c) {
            $ids=array_keys($mediaScenes[P::key($c['asset'])]??[]);
            if(count($ids)===1 && isset($scenes[$ids[0]])) $scenes[$ids[0]]['pictureFrames']+=$c['durationFrames']; else $unassigned+=$c['durationFrames'];
        }
        foreach($scenes as &$s) $s['pictureSeconds']=$s['pictureFrames']/($edit['fps']??30);unset($s);
        return ['work'=>$work,'sceneCount'=>count($scenes),'scenes'=>array_values($scenes),'durationBasis'=>'Sum of selected picture clips assigned to each scene; excludes ambiguous or unassigned clips. Includes title/card scenes. Catalog scene assignments may need editorial correction.','unassignedPictureFrames'=>$unassigned];
    }
    public function longestScene(string $work): array
    {
        $r=$this->scenes($work);$scenes=$r['scenes'];usort($scenes,fn($a,$b)=>$b['pictureFrames']<=>$a['pictureFrames']);
        return ['work'=>$work,'scene'=>$scenes[0]??null,'durationBasis'=>$r['durationBasis'],'unassignedPictureFrames'=>$r['unassignedPictureFrames']];
    }
    public function screenplay(string $work): array
    {
        $entities=$this->catalog->entities($work);$names=[];$title=$work;
        foreach($entities as $e) {
            if($e['entityType']==='Character') $names[P::key($e)]=$e['name'];
            if($e['entityType']==='CreativeWork') $title=$e['name'];
        }
        $text='Title: '.$title."\n\n";
        foreach($entities as $e) if($e['entityType']==='NarrativeScene') {
            $text.='.'. $e['name'].' [[scene '.$e['sceneNumber']."]]\n\n";
            foreach(P::extension($e,'rph.scene/1')['elements']??[] as $line) {
                if($line['type']==='dialogue') {
                    $text.=($names[P::key($line['speaker'])]??'UNKNOWN').' [[line '.$line['id']."]]\n";
                    if($say=$line['direction']['say']??null) $text.='('.$say.")\n";
                    $text.=$line['text']."\n\n";
                } elseif($line['type']==='action') $text.=$line['text']."\n\n";
            }
        }
        return ['work'=>$work,'format'=>'fountain','basis'=>'Script scene order, not final edit order. Inline notes preserve IDs. Describes authored action, not verified image content.','text'=>$text];
    }
    public function characterLines(string $work,string $character): array
    {
        $entities=$this->catalog->entities($work);$matches=[];$names=[];$audio=[];
        foreach($entities as $e) {
            if($e['entityType']==='Character') {
                $names[P::key($e)]=$e['name'];
                if(strcasecmp($e['name'],$character)===0 || strcasecmp($e['identifier'][0]['identifierValue'],'character/'.strtolower($character))===0) $matches[]=$e;
            }
            $edit=P::extension($e,'rph.edit/1');
            foreach($edit['tracks']??[] as $t) if($t['name']==='Dialogue') foreach($t['clips'] as $c) $audio[$c['extras']['metadata']['rph']['element']??'']=$c;
        }
        if(count($matches)!==1) throw new \InvalidArgumentException('Unknown or ambiguous character. Available: '.implode(', ',array_values($names)));
        $cast=$matches[0];$id=P::key($cast);$result=[];
        foreach($entities as $e) if($e['entityType']==='NarrativeScene') {
            $elements=P::extension($e,'rph.scene/1')['elements']??[];$number=0;
            foreach($elements as $i=>$l) {
                if($l['type']!=='dialogue') continue;$number++;
                if(!isset($l['speaker']) || P::key($l['speaker'])!==$id) continue;
                $context=[];
                foreach(array_slice($elements,max(0,$i-2),5) as $near) $context[]=['id'=>$near['id'],'type'=>$near['type'],'speaker'=>isset($near['speaker'])?($names[P::key($near['speaker'])]??null):null,'text'=>$near['text']];
                $result[]=['id'=>$l['id'],'audioReference'=>ltrim((string)$e['sceneNumber'],'0').'.'.$number,'scene'=>$e['sceneNumber'],'heading'=>$e['name'],'text'=>$l['text'],'delivery'=>$l['direction']??[],'context'=>$context,'selectedAudioClip'=>$audio[$l['id']]??null];
            }
        }
        return ['work'=>$work,'character'=>$cast,'lineCount'=>count($result),'countBasis'=>'Dialogue elements, not sentences or screen-wrapped lines.','lines'=>$result,'reviewGuidance'=>'Review the complete character arc, warmth, agency and response to others before proposing changes. Preserve stable line IDs. These tools do not mutate the work.'];
    }
}
