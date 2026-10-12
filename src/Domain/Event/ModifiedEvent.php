<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class ModifiedEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'modified';
    }
}
