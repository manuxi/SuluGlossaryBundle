<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class CopiedLanguageEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'copied_language';
    }
}
