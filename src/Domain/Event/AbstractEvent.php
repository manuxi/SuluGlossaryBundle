<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $glossaryEntryId,
        private readonly ?string $title = null,
        private readonly ?string $locale = null,
        private readonly array $payload = [],
    ) {
        parent::__construct();
    }

    public function getGlossaryEntryId(): string
    {
        return $this->glossaryEntryId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEventPayload(): ?array
    {
        return $this->payload;
    }

    public function getResourceKey(): string
    {
        return GlossaryEntry::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->glossaryEntryId;
    }

    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceTitle(): ?string
    {
        return $this->title;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return GlossaryEntry::SECURITY_CONTEXT;
    }
}
