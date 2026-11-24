<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
/** SQLite-backed catalog adapter shared by application query services. */
final readonly class SqliteWorkCatalog implements WorkCatalog
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $directory) {}
    public function works(): array
    {
        $works=[];
        foreach (glob($this->directory.'/catalogs/*.sqlite') as $file) {
            $slug=basename($file,'.sqlite'); $p=new Production($slug,$this->directory);
            $works[]=['work'=>$slug,'title'=>$p->project()['name']];
        }
        return $works;
    }
    public function revision(string $work): int { $this->entities($work); return (new \Survos\Omc\Storage\WorkingCatalog($this->directory.'/catalogs/'.$work.'.sqlite'))->revision(); }
    public function entities(string $work): array { return (new Production($work,$this->directory))->all(); }
}
