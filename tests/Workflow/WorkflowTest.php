<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\{Asset, Scene, Script};
use App\Workflow\{AssetWorkflow, ScriptWorkflow};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Registry;

final class WorkflowTest extends KernelTestCase
{
    public function testEditorialApprovalRequiresReview(): void
    {
        self::bootKernel();
        $script = new Script('test', 'Test', 'test.fountain', '', null, null, null);
        $workflow = self::getContainer()->get(Registry::class)->get($script, 'script');
        self::assertFalse($workflow->can($script, ScriptWorkflow::APPROVE));
        $workflow->apply($script, ScriptWorkflow::REVIEW_SCRIPT, ['cascade' => 'none']);
        $workflow->apply($script, ScriptWorkflow::APPROVE, ['cascade' => 'none']);
        self::assertSame('ready', $script->marking);
    }

    public function testUnsupportedRendererCannotBeQueuedAndFailureCanRetry(): void
    {
        self::bootKernel();
        $script = new Script('test', 'Test', 'test.fountain', '', null, null, null);
        $scene = new Scene('test-1', $script, 1, 'INT. ROOM - DAY');
        $polished = new Asset($scene, 'polished', 'Description', 'Prompt', []);
        $stick = new Asset($scene, 'stick', 'Description', 'Prompt', []);
        $workflow = self::getContainer()->get(Registry::class)->get($stick, 'asset');
        self::assertFalse($workflow->can($polished, AssetWorkflow::QUEUE));
        self::assertTrue($workflow->can($stick, AssetWorkflow::QUEUE));
        self::assertFalse($workflow->can($stick, AssetWorkflow::GENERATE));
        $stick->marking = AssetWorkflow::FAILED;
        self::assertTrue($workflow->can($stick, AssetWorkflow::QUEUE));
        self::assertSame('none', $stick->quality);
    }
}
