<?php
declare(strict_types=1);
namespace App\Command;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\Console\Attribute\{AsCommand,Argument};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
#[AsCommand('app:catalog:import','Import an OMC work once into its authoritative SQLite catalog')]
final readonly class ImportWorkingCatalogCommand
{
 public function __construct(#[Autowire('%kernel.project_dir%')] private string $root) {}
 public function __invoke(SymfonyStyle $io,#[Argument] string $work): int {
  if(!preg_match('/^[a-z0-9-]+$/D',$work))throw new \InvalidArgumentException('Invalid work.');
  $dir=$this->root.'/data';$path=$dir.'/catalogs/'.$work.'.sqlite';
  if(is_file($path))throw new \RuntimeException('Working database already exists; import cannot overwrite edits.');
  $project=file_get_contents($dir.'/'.$work.'/project.json');$entities=json_decode($project,true,flags:JSON_THROW_ON_ERROR);$files=\App\Omc\Production::extension($entities[0],'rph.project/1')['files'];$docs=['project.json'=>$project];
  foreach($files as $file){if(!preg_match('/^[a-z-]+\.json$/D',$file))throw new \InvalidArgumentException('Invalid import filename.');$docs[$file]=file_get_contents($dir.'/'.$work.'/'.$file);}
  if(!is_dir(dirname($path)))mkdir(dirname($path),0775,true);$temporary=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
  try{$store=new WorkingCatalog($temporary);$store->importDocuments($docs,'rph:migration');foreach($store->exportDocuments() as $file=>$json)if(json_decode($json)!=json_decode($docs[$file]))throw new \RuntimeException('Roundtrip mismatch: '.$file);unset($store);rename($temporary,$path);}finally{if(is_file($temporary))unlink($temporary);}
  $io->success($work.' imported and semantic roundtrip verified.');return 0;
 }
}
