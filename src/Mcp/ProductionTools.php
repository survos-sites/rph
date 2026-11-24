<?php
declare(strict_types=1);
namespace App\Mcp;
use App\Production\WorkQueries;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\RequestContext;
final readonly class ProductionTools
{
    public function __construct(private WorkQueries $queries, private \App\Production\ProductionEditor $editor, private \App\Production\ShotEditor $shots, private \App\Production\CharacterEditor $characters, private \App\Production\EditPresentation $presentation, private \App\Production\SequenceEditor $sequence, private \App\Production\MessageCards $cards) {}
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
    #[McpTool(name:'revise_line',description:'Revise a stable script element ID in the selected work with an expected revision. Dialogue regenerates and selects a versioned local voice take atomically, preserving its existing time slot. Rejects excessive compression. Action edits update text only.')]
    public function reviseLine(string $lineId,string $text,string $delivery,int $expectedRevision,RequestContext $context,?string $work=null): array
    {
        return $this->editor->reviseLine($this->work($context,$work),$lineId,$text,$delivery,$expectedRevision);
    }
    #[McpTool(name:'get_shot', description:'Read a shot by stable identifier, including its storyboard layers, blocking and current revision.', annotations:new ToolAnnotations(readOnlyHint:true))]
    public function getShot(string $shotId, RequestContext $context, ?string $work=null): array
    { return $this->shots->inspect($this->work($context,$work),$shotId); }
    #[McpTool(name:'revise_storyboard', description:'Replace a storyboard recipe and render/select a versioned asset, preserving timing. storyboardJson contains backdrop, description and layers; character layers use character references, pose, expression, box. The final layer may have entrance: {fromX,frames} for horizontal entrance animation. Requires expectedRevision.')]
    public function reviseStoryboard(string $shotId, string $storyboardJson, int $expectedRevision, RequestContext $context, ?string $work=null): array
    { return $this->shots->revise($this->work($context,$work),$shotId,$storyboardJson,$expectedRevision); }
    #[McpTool(name:'revise_character', description:'Update a character casting profile using its stable character/id and expected revision. profileJson supports age, gender, biography, relationships, voice, visualDescription, appearance, outfits, castingStatus, description and native background. Existing appearance and voice settings are merged. Does not regenerate existing assets.')]
    public function reviseCharacter(string $characterId, string $profileJson, int $expectedRevision, RequestContext $context, ?string $work=null): array
    { return $this->characters->revise($this->work($context,$work),$characterId,$profileJson,$expectedRevision); }
    #[McpTool(name:'revise_scene_heading', description:'Change a scene heading while preserving stable identity and timings; requires current revision.')]
    public function reviseSceneHeading(string $sceneId,string $heading,int $expectedRevision,RequestContext $context,?string $work=null): array
    { return $this->editor->reviseSceneHeading($this->work($context,$work),$sceneId,$heading,$expectedRevision); }
    #[McpTool(name:'draft_line', description:'Edit dialogue for subtitle-first review without generating audio. Marks the existing take stale and mutes it in playback; returns estimated reading frames. Existing timeline timing remains until explicitly retimed.')]
    public function draftLine(string $lineId,string $text,string $delivery,int $expectedRevision,RequestContext $context,?string $work=null): array
    { return $this->editor->reviseLine($this->work($context,$work),$lineId,$text,$delivery,$expectedRevision,false); }
    #[McpTool(name:'revise_presentation', description:'Set a selected shot clip presentation (motion, layout, caption, eyebrow, fades). Optional durationFrames can shorten or extend the final picture clip for credits; updates shot timing and track bounds atomically. Optional title updates the displayed clip title.')]
    public function revisePresentation(string $shotId,string $presentationJson,int $expectedRevision,RequestContext $context,?string $work=null,?int $durationFrames=null,?string $title=null): array
    { return $this->presentation->change($this->work($context,$work),$shotId,$presentationJson,$expectedRevision,$durationFrames,$title); }
    #[McpTool(name:'replace_tail_sequence', description:'Atomically recut the movie from fromFrame onward using existing selected assets. scheduleJson has pictures [{shot,frames}] in playback order and dialogue [{line,offset,frames}] relative to fromFrame. Unscheduled tail clips/audio are removed from the edit, source entities retained; tracks and shot timing updated. For subtitle-first editing, draft lines before recutting.')]
    public function replaceTailSequence(int $fromFrame,string $scheduleJson,int $expectedRevision,RequestContext $context,?string $work=null): array
    { return $this->sequence->replaceTail($this->work($context,$work),$fromFrame,$scheduleJson,$expectedRevision); }
    #[McpTool(name:'insert_message_cards', description:'Insert attributed message cards immediately before the credits. cardsJson is [{speaker,text,frames}]. Preserves credits, shifts their timing, updates track lengths. Refuses duplicate insertion.')]
    public function insertMessageCards(string $cardsJson,int $expectedRevision,RequestContext $context,?string $work=null): array
    { return $this->cards->insert($this->work($context,$work),$cardsJson,$expectedRevision); }
    #[McpTool(name:'style_inspiration_chat', description:'Present the existing inspiration messages as a wide family chat, preserving all text and timings, with sender bubbles and previous-message context.')]
    public function styleInspirationChat(int $expectedRevision,RequestContext $context,?string $work=null): array
    { return $this->cards->styleChat($this->work($context,$work),$expectedRevision); }
    private function work(RequestContext $context,?string $work): string
    {
        $work ??= $context->getSession()->get('rph_work');
        if(!$work) throw new \InvalidArgumentException('Select a work with set_work or provide work explicitly.');
        return $work;
    }
}
