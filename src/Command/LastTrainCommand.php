<?php
namespace App\Command;

use App\Entity\{Script, Scene, Character, ScriptElement};
use App\Enum\ElementType;
use App\Timeline\AnimaticBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Console\Attribute\{AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\Registry;

#[AsCommand('app:last-train', 'Create The Last Train cast and SVG shot-list animatic')]
final readonly class LastTrainCommand
{
    public function __construct(private EntityManagerInterface $em, private TransitionExecutorInterface $executor, private Registry $workflows, private AnimaticBuilder $builder, #[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function __invoke(SymfonyStyle $io, #[Option('Run pose transitions synchronously')] bool $sync = false, #[Option('Assemble without rebuilding poses')] bool $skipPoses = false): int
    {
        $data = json_decode(file_get_contents($this->dir.'/data/animatics/last-train.json'), true, flags: JSON_THROW_ON_ERROR);
        $script = $this->em->find(Script::class, 'last-train');
        $create = function () use (&$script, $data, $skipPoses): void {
            if (!$script) {
                $script = new Script('last-train', 'The Last Train', 'last-train.json', implode("\n\n", array_column($data['cues'], 'text')), 'Original scene', 'RPH', 'CC0');
                $this->em->persist($script);
                $scene = new Scene('last-train-1', $script, 1, 'EXT. NORTH PLATFORM — BLUE HOUR');
                $script->scenes->add($scene); $this->em->persist($scene);
                $cast = [];
                foreach ($data['characters'] as $c) {
                    $role = new Character('last-train:'.$c['id'], $script, $c['name']);
                    $role->appearance = ['skin'=>$c['skin'], 'hair'=>$c['hair'], 'clothing'=>$c['color'], 'faceShape'=>$c['id']==='eli'?'oval':'round', 'glasses'=>$c['id']==='eli', 'description'=>$c['castingBrief']['dramaticRole']];
                    $script->characters->add($role); $this->em->persist($role); $cast[$c['id']] = $role;
                }
                foreach ($data['cues'] as $i=>$cue) {
                    $element = new ScriptElement('last-train-1:'.($i+1), $scene, $cast[$cue['speaker']], $i+1, ElementType::Dialogue, $cue['text']);
                    $scene->elements->add($element); $this->em->persist($element);
                }
                $this->em->flush(); // bundle postFlush -> next -> character.build
            } else {
                foreach ($script->characters as $role) { $role->poses = array_values(array_unique([...$role->poses, 'sitting-right', 'sitting-left'])); }
                $this->em->flush();
                if (!$skipPoses) foreach ($script->characters as $role) { $this->executor->execute($this->workflows->get($role, 'character'), $role, 'build'); }
            }
        };
        $sync ? $this->executor->synchronously($create) : $create();
        $this->em->refresh($script);
        foreach ($script->characters as $role) { $this->em->refresh($role); }
        if (array_filter($script->characters->toArray(), fn($c)=>!$c->poseAssets)) {
            $io->success('Characters saved; postFlush queued their pose builds. Consume character.build, then run this command with --sync to assemble.'); return 0;
        }
        $profiles = json_decode(file_get_contents($this->dir.'/data/cast/last-train.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($script->characters as $role) {
            foreach ($data['characters'] as $definition) if ($definition['name'] === $role->name) {
                $role->age ??= $definition['castingBrief']['age'];
                $role->gender ??= $definition['castingBrief']['gender'];
            }
            if (!$role->voiceProfile) $role->voiceProfile = $profiles[$role->name];
            $role->voiceDescription ??= $role->name === 'Mara Vale'
                ? 'A grounded middle-aged woman, warm mid register, clear American accent. Practical, quietly funny, concern beneath professional composure.'
                : 'A middle-aged man with a low warm voice and American accent. Measured, gently eccentric, dry wit; tender when recalling his sister.';
        }
        foreach ($script->scenes as $scene) foreach ($scene->elements as $line) {
            $cue = $data['cues'][$line->sequence-1];
            $line->delivery ??= $cue['delivery'];
            $line->notes ??= $cue['voiceNotes'];
        }
        $this->em->flush();
        $this->builder->build($script, $data);
        $io->success('SVG shot list ready: /scripts/last-train/animatic'); return 0;
    }
}
