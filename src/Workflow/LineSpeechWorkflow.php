<?php
namespace App\Workflow;

use App\Entity\LineSpeech;
use App\Timeline\{AnimaticBuilder, TimelineMedia};
use App\Voice\SpeechSynthesizer;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowMessengerBundle\Attribute\Transition;
use Symfony\Component\Workflow\Attribute\{AsWorkflow, AsTransitionListener};
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Symfony\Component\Filesystem\Filesystem;

#[AsWorkflow(name: 'line_voice', supports: [LineSpeech::class])]
final readonly class LineSpeechWorkflow
{
    #[Place(initial: true, next: self::GENERATE)] public const PENDING = 'pending';
    #[Place] public const READY = 'ready';
    #[Transition(from: self::PENDING, to: self::READY, async: true, transport: 'line_voice.generate')]
    public const GENERATE = 'generate';

    public function __construct(
        #[AutowireIterator('rph.speech_synthesizer')] private iterable $synthesizers,
        private TimelineMedia $media,
        private AnimaticBuilder $animatic,
        #[Autowire('%kernel.project_dir%')] private string $dir,
    ) {}

    #[AsTransitionListener('line_voice', self::GENERATE)]
    public function generate(TransitionEvent $event): void
    {
        /** @var LineSpeech $request */
        $request = $event->getSubject();
        $voice = $request->spec();
        $url = '/media/voices/'.$voice->key($request->text).'.mp3';
        $target = $this->dir.'/public'.$url;
        if ($request->force || !is_file($target)) {
            $engine = null;
            foreach ($this->synthesizers as $candidate) {
                if ($candidate->engine() === $voice->engine) { $engine = $candidate; break; }
            }
            if (!$engine) throw new \LogicException('No synthesizer for '.$voice->engine);
            if ($reason = $engine->unavailableReason()) throw new \RuntimeException($reason);
            (new Filesystem())->mkdir(dirname($target));
            $engine->synthesize($request->text, $voice, $target);
        }
        $request->duration = $this->media->duration($url);
        if ($request->duration <= 0) throw new \RuntimeException('Generated audio is empty.');
        [$request->speechStart, $request->speechDuration] = $this->media->speechRange($url);
        $request->audioUrl = $url;
        // The active request alone affects the edit; an older queued performance stays archived.
        $script = $request->element->scene->script;
        $source = $this->dir.'/data/animatics/'.$script->id.'.json';
        if ($request->element->speech === $request && is_file($source)) {
            $this->animatic->build($script, json_decode(file_get_contents($source), true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
