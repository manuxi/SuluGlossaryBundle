<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Link;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfigurationBuilder;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryEntryLinkProvider implements LinkProviderInterface
{
    public function __construct(
        private readonly ContentManagerInterface $contentManager,
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ReferenceStoreInterface $referenceStore,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getConfigurationBuilder(): LinkConfigurationBuilder
    {
        return LinkConfigurationBuilder::create()
            ->setTitle($this->translator->trans('sulu_glossary.glossary', [], 'admin'))
            ->setResourceKey(GlossaryEntry::RESOURCE_KEY)
            ->setListAdapter('table')
            ->setDisplayProperties(['title'])
            ->setOverlayTitle($this->translator->trans('sulu_glossary.select_glossary_entry', [], 'admin'))
            ->setEmptyText($this->translator->trans('sulu_glossary.no_glossary_entry_selected', [], 'admin'))
            ->setIcon('su-tag-pen');
    }

    public function preload(array $hrefs, string $locale, bool $published = true): iterable
    {
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => $published ? DimensionContentInterface::STAGE_LIVE : DimensionContentInterface::STAGE_DRAFT,
        ];

        $glossaryEntries = $this->repository->findBy(
            [...$dimensionAttributes, 'uuids' => array_map('strval', $hrefs)],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        );

        $result = [];
        foreach ($glossaryEntries as $glossaryEntry) {
            try {
                $dimensionContent = $this->contentManager->resolve($glossaryEntry, $dimensionAttributes);
            } catch (ContentNotFoundException) {
                continue;
            }

            $this->referenceStore->add($glossaryEntry->getUuid(), GlossaryEntry::RESOURCE_KEY);

            $url = $dimensionContent->getRoute()?->getSlug();
            if (null === $url) {
                continue;
            }

            $result[] = new LinkItem($glossaryEntry->getUuid(), (string) $dimensionContent->getTitle(), $url, $published);
        }

        return $result;
    }
}
