<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Domain\Event\Settings;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly GlossaryEntrySettings $settings,
        private readonly array $payload = [],
    ) {
        parent::__construct();
    }

    public function getSettings(): GlossaryEntrySettings
    {
        return $this->settings;
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
        return GlossaryEntrySettings::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->settings->getId();
    }

    public function getResourceTitle(): ?string
    {
        return 'Glossary Settings';
    }

    public function getResourceSecurityContext(): ?string
    {
        return GlossaryEntrySettings::SECURITY_CONTEXT;
    }
}
