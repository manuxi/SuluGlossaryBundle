<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class UnpublishedEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'unpublished';
    }
}
