<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches reindex messages for the changed glossary entry so the admin and the website
 * search index follow the draft and the live content.
 */
class GlossaryEntryIndexListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onDraftChanged(AbstractEvent $event): void
    {
        $this->dispatch($event, GlossaryEntryAdminReindexProvider::INDEX);
    }

    public function onLiveChanged(AbstractEvent $event): void
    {
        $this->dispatch($event, GlossaryEntryWebsiteReindexProvider::INDEX);
    }

    private function dispatch(AbstractEvent $event, string $index): void
    {
        $locales = $event->getAllLocales() ?? (null !== $event->getLocale() ? [$event->getLocale()] : []);

        $identifiers = array_map(
            static fn (string $locale): string => GlossaryEntry::RESOURCE_KEY . '__' . $event->getResourceId() . '__' . $locale,
            $locales,
        );

        if ([] === $identifiers) {
            return;
        }

        $this->messageBus->dispatch(
            ReindexConfig::create()->withIndex($index)->withIdentifiers($identifiers),
        );
    }
}
