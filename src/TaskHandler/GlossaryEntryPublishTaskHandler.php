<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\TaskHandler;

use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Sulu\Content\Domain\Model\WorkflowInterface;

class GlossaryEntryPublishTaskHandler extends AbstractWorkflowTaskHandler
{
    protected function getTitle(): string
    {
        return 'sulu_content.task_handler.publish';
    }

    protected function getTransition(): string
    {
        return WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH;
    }

    protected function createEvent(string $id, string $locale): AbstractEvent
    {
        return new PublishedEvent($id, null, $locale, ['automation' => true]);
    }
}
