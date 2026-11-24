<?php

declare(strict_types=1);
namespace App\Command;

use App\Omc\Production;
use Symfony\Component\Console\Attribute\{AsCommand, Argument};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand('app:omc:motion', 'Composite catalog SVG layers with frame-based movement into playable video')]
final readonly class RenderOmcMotionCommand
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function __invoke(SymfonyStyle $io, #[Argument] string $slug): int
    {
        throw new \LogicException('Legacy JSON writer disabled. Use catalog editing services for SQLite-backed works.');
        $p = new Production($slug, $this->dir.'/data'); $fs = new Filesystem(); $updates = [];
        foreach ($p->all('Asset') as $asset) {
            $shot = Production::extension($asset,'rph.shot/1');
            if (!isset($shot['motionLayers'])) continue;
            $plan = $shot['motionLayers']; $inputs = []; $signature = json_encode([$plan,$shot['durationFrames'],$p->edit()['fps']]);
            foreach (['background','foreground'] as $layer) {
                $path = $plan[$layer];
                if (!preg_match('~^data/storyboards/[a-z0-9/-]+\.svg$~D', $path) || str_contains($path,'..')) throw new \InvalidArgumentException('Invalid motion layer path.');
                $inputs[] = $this->dir.'/'.$path; $signature .= file_get_contents(end($inputs));
            }
            $key = substr(hash('sha256',$signature),0,24);
            $url = '/media/generated/'.$slug.'/motion/'.$key.'.mp4';
            $target = $this->dir.'/public'.$url; $fs->mkdir(dirname($target));
            if (!is_file($target)) {
                foreach ($inputs as $i=>$input) (new Process(['rsvg-convert','-o',dirname($target).'/'.$key.'-'.$i.'.png',$input]))->mustRun();
                $x = $this->expression($plan['keyframes'],'x'); $y = $this->expression($plan['keyframes'],'y');
                $fps = $p->edit()['fps'];
                $filter = "[0:v][1:v]overlay=x='$x':y='$y':eval=frame:format=auto,format=yuv420p[out]";
                $process = new Process(['ffmpeg','-y','-v','error','-loop','1','-framerate',(string)$fps,'-i',dirname($target).'/'.$key.'-0.png','-loop','1','-framerate',(string)$fps,'-i',dirname($target).'/'.$key.'-1.png','-filter_complex',$filter,'-map','[out]','-frames:v',(string)$shot['durationFrames'],'-an','-c:v','libx264','-preset','fast','-crf','18','-movflags','+faststart',$target.'.tmp.mp4']);
                $process->setTimeout(120); $process->mustRun(); $fs->rename($target.'.tmp.mp4',$target);
            }
            $poster = substr($target, 0, -4).'.jpg';
            if (!is_file($poster)) (new Process(['ffmpeg','-y','-v','error','-ss','2','-i',$target,'-frames:v','1',$poster]))->mustRun();
            $updates[Production::key($shot['media'])] = $url;
        }
        foreach (Production::extension($p->project(),'rph.project/1')['files'] as $file) {
            $path=$this->dir.'/data/'.$slug.'/'.$file;
            $entities=json_decode(file_get_contents($path)); $changed=false;
            foreach ($entities as $entity) {
                $key=Production::key(json_decode(json_encode($entity),true));
                if (!isset($updates[$key])) continue;
                foreach ($entity->customData as $custom) if ($custom->namespace==='rph.media/1') {
                    $custom->value->poster = substr($updates[$key],0,-4).'.jpg';
                    $custom->value->url=$updates[$key]; $custom->value->kind='video'; $custom->value->credit=''; $custom->value->renderer='svg-layer-motion/1'; $changed=true;
                }
            }
            if ($changed) $fs->dumpFile($path,json_encode($entities,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
        }
        $io->success(sprintf('Rendered %d layered motion shots.',count($updates))); return 0;
    }
    private function expression(array $keys,string $axis): string
    {
        if (count($keys)<2) throw new \InvalidArgumentException('Motion requires at least two keyframes.');
        foreach ($keys as $i=>$key) if (!is_int($key['frame']) || !is_numeric($key[$axis]) || ($i>0 && $key['frame'] <= $keys[$i-1]['frame'])) throw new \InvalidArgumentException('Invalid motion keyframes.');
        $expr=(string)$keys[array_key_last($keys)][$axis];
        for ($i=count($keys)-2;$i>=0;--$i) {
            $a=$keys[$i]; $b=$keys[$i+1]; $span=$b['frame']-$a['frame']; $delta=$b[$axis]-$a[$axis];
            $expr=sprintf('if(lt(n-1,%d),%s+(%s)*max(0,n-1-%d)/%d,%s)',$b['frame'],$a[$axis],$delta,$a['frame'],$span,$expr);
        }
        return $expr;
    }
}
