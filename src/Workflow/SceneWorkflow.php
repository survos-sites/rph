<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Scene;
use Survos\WorkflowExtrasBundle\Attribute\{AsWorkflow, Place, Transition};

#[AsWorkflow(name: 'scene', supports: Scene::class, description: 'Scene preparation and editorial approval, independent of rendering')]
final class SceneWorkflow
{
    #[Place('Imported scene awaiting preparation', initial: true)]
    public const DRAFT = 'draft';
    #[Place('Scene blocking, cast and directions under review')]
    public const REVIEW = 'review';
    #[Place('Scene approved for production')]
    public const READY = 'ready';
    #[Transition(self::DRAFT, self::REVIEW, 'Begin scene preparation')]
    public const PREPARE = 'prepare';
    #[Transition(self::REVIEW, self::READY, 'Approve scene preparation')]
    public const APPROVE = 'approve';
    #[Transition(self::READY, self::REVIEW, 'Revise scene preparation')]
    public const REVISE = 'revise';
}
