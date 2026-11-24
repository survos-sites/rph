<?php
declare(strict_types=1);
namespace App\Mcp;
use App\Production\WorkQueries;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\RequestContext;
final readonly class ProductionTools
{
    public function __construct(private WorkQueries $queries) {}
    #[McpTool(name:'list_works',description:'List available production works and their slugs.',annotations:new ToolAnnotations(readOnlyHint:true))]
    public function listWorks(): array { return $this->queries->works(); }
    #[McpTool(name:'set_work',description:'Select a work such as xmas for this MCP session. Does not edit production data. Pass work explicitly on queries for stateless clients.')]
    public function setWork(string $work,RequestContext $context): array
    {
        $summary=$this->queries->scenes($work);
        $context->getSession()->set('rph_work',$work);$context->getSession()->save();
        return ['selectedWork'=>$work,'sceneCount'=>$summary['sceneCount']];
    }
    #[McpTool(name:'get_scenes',description:'Count and list scenes, dialogue counts and selected picture durations for the selected or explicit work.',annotations:new ToolAnnotations(readOnlyHint:true))]
    public function scenes(RequestContext $context,?string $work=null): array { return $this->queries->scenes($this->work($context,$work)); }
    #[McpTool(name:'get_longest_scene',description:'Find the scene with the most selected picture time in the current edit. Returns the duration basis and unassigned frames.',annotations:new ToolAnnotations(readOnlyHint:true))]
    public function longestScene(RequestContext $context,?string $work=null): array { return $this->queries->longestScene($this->work($context,$work)); }
    #[McpTool(name:'get_character_lines',description:'Get ALL dialogue for a character, in script order, with total count, character brief, stable line IDs, audio references, delivery and surrounding context. Use this to review requests such as make Jess more likeable; no vector search needed.',annotations:new ToolAnnotations(readOnlyHint:true))]
    public function characterLines(string $character,RequestContext $context,?string $work=null): array { return $this->queries->characterLines($this->work($context,$work),$character); }
    #[McpTool(name:'get_screenplay',description:'Read the complete work as Fountain: scene headings, action, dialogue, delivery notes and stable line IDs. Use for story-level feedback before proposing edits.',annotations:new ToolAnnotations(readOnlyHint:true))]
    public function screenplay(RequestContext $context,?string $work=null): array { return $this->queries->screenplay($this->work($context,$work)); }
    private function work(RequestContext $context,?string $work): string
    {
        $work ??= $context->getSession()->get('rph_work');
        if(!$work) throw new \InvalidArgumentException('Select a work with set_work or provide work explicitly.');
        return $work;
    }
}
