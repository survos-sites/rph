<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{Asset, Scene};
use App\Service\ScenePlayerService;
use App\Timeline\SceneArtwork;
use App\Workflow\AssetWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\Registry;

#[AsCommand(name: 'app:generate-scene-asset', description: 'Save a scene artwork specification and queue its SVG renderer')]
final class GenerateSceneAssetCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ScenePlayerService $player, private readonly SceneArtwork $artwork, private readonly Registry $workflows, private readonly TransitionExecutorInterface $executor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('scene', InputArgument::REQUIRED, 'Scene ID')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Render through the same workflow in this process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scene = $this->em->find(Scene::class, $input->getArgument('scene'));
        if (!$scene) {
            $io->error('Scene not found.');
            return Command::INVALID;
        }
        $payload = $this->player->payload($scene->script, $scene);
        $description = $this->artwork->describe($payload);
        $asset = new Asset($scene, 'stick', $description['placeholderDescription'], $description['imagePrompt'], [
            'version' => 1, 'renderer' => 'rph-stick-svg/v1',
            'scene' => $payload['scene'], 'cast' => array_slice($payload['cast'], 0, 5), 'direction' => $description,
        ]);
        $this->em->persist($asset);
        $this->em->flush();
        $queue = function () use ($asset): void {
            $this->workflows->get($asset, 'asset')->apply($asset, AssetWorkflow::QUEUE);
            // WorkflowMessenger dispatches the next transition only after this save.
            $this->em->flush();
        };
        if ($input->getOption('sync')) {
            $this->executor->synchronously($queue);
        } else {
            $queue();
        }
        $io->success(sprintf('Asset %s: %s%s', $asset->id, $asset->marking, $asset->uri ? ' — '.$asset->uri : ''));
        return Command::SUCCESS;
    }
}
