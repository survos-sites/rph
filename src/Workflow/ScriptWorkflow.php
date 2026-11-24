<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Script;
use Survos\WorkflowExtrasBundle\Attribute\{AsWorkflow, Place, Transition};

#[AsWorkflow(name: 'script', supports: Script::class, description: 'Editorial review of an imported script')]
final class ScriptWorkflow
{
    #[Place('Imported source, awaiting review', initial: true)]
    public const IMPORTED = 'imported';
    #[Place('Script structure and content under review')]
    public const REVIEW = 'review';
    #[Place('Script approved for production')]
    public const READY = 'ready';
    #[Transition(self::IMPORTED, self::REVIEW, 'Begin reviewing the imported script')]
    public const REVIEW_SCRIPT = 'review';
    #[Transition(self::REVIEW, self::READY, 'Approve the script for production')]
    public const APPROVE = 'approve';
    #[Transition(self::READY, self::REVIEW, 'Reopen editorial review')]
    public const REVISE = 'revise';
}
