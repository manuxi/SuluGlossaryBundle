<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class DraftRemovedEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'draft_removed';
    }
}
