<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
/** Transitional read adapter; replace with working SQLite catalog without changing MCP tools. */
final readonly class JsonWorkCatalog implements WorkCatalog
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $directory) {}
    public function works(): array
    {
        $works=[];
        foreach (glob($this->directory.'/*/project.json') as $file) {
            $slug=basename(dirname($file)); $p=new Production($slug,$this->directory);
            $works[]=['work'=>$slug,'title'=>$p->project()['name']];
        }
        return $works;
    }
    public function entities(string $work): array { return (new Production($work,$this->directory))->all(); }
}
