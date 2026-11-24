<?php
declare(strict_types=1);
namespace App\Mcp;
use App\Omc\PreviewModel;
use Mcp\Server\RequestContext;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
#[AsMcpApp(uri:'ui://rph/preview', name:'preview_work', title:'RPH movie preview', description:'Play the entire current movie, or an optional scene, as a live OTIO composition. No MP4 rendering. Uses the selected work unless work is supplied.', template:'mcp/preview.html.twig', cspFrame:['https://rph.wip'], cspResource:['https://rph.wip'], prefersBorder:true)]
final readonly class PreviewApp
{
    public function __construct(private PreviewModel $preview) {}
    public function render(RequestContext $context,?string $work=null,?string $scene=null): array
    {
        $work ??= $context->getSession()->get('rph_work');
        if(!$work) throw new \InvalidArgumentException('Select a work first or pass work.');
        return $this->preview->get($work,$scene);
    }
}
