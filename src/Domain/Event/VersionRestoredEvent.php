<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class VersionRestoredEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'version_restored';
    }
}
