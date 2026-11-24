<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Asset;
use Survos\WorkflowExtrasBundle\Attribute\{AsWorkflow, Place, Transition};
use Survos\WorkflowMessengerBundle\Attribute\Transition as AsyncTransition;

#[AsWorkflow(name: 'asset', supports: Asset::class, description: 'Durable asynchronous artwork generation')]
final class AssetWorkflow
{
    #[Place('Generation specification saved', initial: true)]
    public const PENDING = 'pending';
    #[Place('Waiting for an artwork worker', next: self::GENERATE)]
    public const QUEUED = 'queued';
    #[Place('Artwork generated successfully')]
    public const READY = 'ready';
    #[Place('Generation exhausted retries')]
    public const FAILED = 'failed';
    #[Transition(self::PENDING, self::QUEUED, 'Queue a supported renderer', guard: "subject.requestedQuality == 'stick'")]
    #[Transition(self::FAILED, self::QUEUED, 'Retry a failed generation', guard: "subject.requestedQuality == 'stick'")]
    public const QUEUE = 'queue';
    #[AsyncTransition(self::QUEUED, self::READY, 'Render the frozen artwork specification', transport: 'async')]
    public const GENERATE = 'generate';
    #[Transition(self::QUEUED, self::FAILED, 'Record an exhausted or unrecoverable generation failure')]
    public const FAIL = 'fail';
}
