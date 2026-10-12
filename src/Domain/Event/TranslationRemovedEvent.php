<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

class TranslationRemovedEvent extends AbstractEvent
{
    public function getEventType(): string
    {
        return 'translation_removed';
    }
}
