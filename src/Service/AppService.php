<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Character;
use App\Entity\Scene;
use App\Entity\Script;
use App\Entity\ScriptElement;
use App\Repository\ScriptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class AppService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ScriptRepository $scripts,
        private readonly FountainParser $parser,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    #[AsCommand('app:load', 'import one Fountain file or every Fountain file in a directory')]
    public function load(SymfonyStyle $io, #[Argument('Fountain file or directory')] string $path = 'data/scripts'): int
    {
        $path = Path::isAbsolute($path) ? $path : Path::join($this->projectDir, $path);
        $files = $this->findScripts($path);
        if ([] === $files) {
            $io->error("No .fountain or .fount files found at $path");

            return Command::FAILURE;
        }

        foreach ($files as $filename) {
            $script = $this->importFile($filename);
            $io->writeln(sprintf('%s: %d scenes, %d characters', $script->title, $script->scenes->count(), $script->characters->count()));
        }
        $this->em->flush();
        $io->success(sprintf('Imported %d script(s).', count($files)));

        return Command::SUCCESS;
    }

    public function importFile(string $filename): Script
    {
        $content = file_get_contents($filename);
        if (false === $content) {
            throw new \RuntimeException("Unable to read $filename");
        }

        $slugger = new AsciiSlugger();
        $id = $slugger->slug(pathinfo($filename, PATHINFO_FILENAME))->lower()->toString();
        $parsed = $this->parser->parse($content, pathinfo($filename, PATHINFO_FILENAME));

        if ($existing = $this->scripts->find($id)) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $script = new Script($id, $parsed->title, basename($filename), $content, $parsed->credit, $parsed->author, $parsed->source);
        $this->em->persist($script);
        $characters = [];

        foreach ($parsed->scenes as $parsedScene) {
            $scene = new Scene($id.'-'.$parsedScene->sequence, $script, $parsedScene->sequence, $parsedScene->heading);
            $script->scenes->add($scene);
            $this->em->persist($scene);

            foreach ($parsedScene->elements as $parsedElement) {
                $character = null;
                if ($parsedElement->speaker) {
                    $characterKey = mb_strtolower($parsedElement->speaker);
                    if (!isset($characters[$characterKey])) {
                        $characterId = $id.':'.$slugger->slug($parsedElement->speaker)->lower();
                        $character = new Character((string) $characterId, $script, $parsedElement->speaker);
                        $characters[$characterKey] = $character;
                        $script->characters->add($character);
                        $this->em->persist($character);
                    }
                    $character = $characters[$characterKey];
                }

                $element = new ScriptElement(
                    $scene->id.':'.$parsedElement->sequence,
                    $scene,
                    $character,
                    $parsedElement->sequence,
                    $parsedElement->type,
                    $parsedElement->text,
                );
                $scene->elements->add($element);
                $this->em->persist($element);
            }
        }

        return $script;
    }

    /** @return list<string> */
    private function findScripts(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        foreach ((new Finder())->files()->in($path)->name(['*.fountain', '*.fount'])->sortByName() as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}
