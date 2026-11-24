<?php

declare(strict_types=1);
namespace App\Production;

use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CharacterEditor
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}

    public function revise(string $work, string $characterId, string $profileJson, int $expectedRevision): array
    {
        $production = new Production($work, $this->data);
        $matches = array_values(array_filter($production->all('Character'), static fn($e)=>$e['identifier'][0]['identifierValue']===$characterId));
        if (count($matches)!==1) throw new \InvalidArgumentException('Unknown character.');
        $character=$matches[0];
        $patch=json_decode($profileJson,true,flags:JSON_THROW_ON_ERROR);
        if (!is_array($patch) || array_is_list($patch)) throw new \InvalidArgumentException('Expected a character profile object.');
        if(isset($patch['age']) && (!is_int($patch['age']) || $patch['age']<0 || $patch['age']>120)) throw new \InvalidArgumentException('Age must be an integer between 0 and 120.');
        $db=new WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite');
        $ids=array_values(array_filter($db->rows('identifier'),static fn($i)=>$i['value']===$characterId && $i['scope']===$character['identifier'][0]['identifierScope']));
        $id=$ids[0]['entity_id'];
        $existing=Production::extension($character,'rph.character/1');
        $revision=$db->revise($expectedRevision,'rph:mcp',function($db)use($id,$patch,$existing,$character){
            foreach($patch as $field=>$value) {
                if($field==='name') { $db->renameEntity($id,$value); $db->setEntityField($id,'characterName',(object)['scriptName'=>$value]); continue; }
                if($field==='description') { $db->setEntityField($id,'description',$value); continue; }
                if($field==='background') {
                    $profile=$character['profile'] ?? [];
                    $profile['background']=$value;
                    if (($profile['physicalCharacteristics'] ?? null) === []) $profile['physicalCharacteristics']=new \stdClass();
                    $db->setEntityField($id,'profile',json_decode(json_encode($profile)));
                    continue;
                }
                if(is_array($value) && !array_is_list($value)) $value=array_replace_recursive($existing[$field] ?? [],$value);
                $db->setCharacterField($id,$field,json_decode(json_encode($value)));
            }
        });
        return ['work'=>$work,'characterId'=>$characterId,'revision'=>$revision,'updatedFields'=>array_keys($patch),'audioRegenerated'=>false];
    }
}
