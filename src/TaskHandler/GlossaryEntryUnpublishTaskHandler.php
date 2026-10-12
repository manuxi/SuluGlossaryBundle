<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\TaskHandler;

use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Sulu\Content\Domain\Model\WorkflowInterface;

class GlossaryEntryUnpublishTaskHandler extends AbstractWorkflowTaskHandler
{
    protected function getTitle(): string
    {
        return 'sulu_content.task_handler.unpublish';
    }

    protected function getTransition(): string
    {
        return WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH;
    }

    protected function createEvent(string $id, string $locale): AbstractEvent
    {
        return new UnpublishedEvent($id, null, $locale, ['automation' => true]);
    }
}
