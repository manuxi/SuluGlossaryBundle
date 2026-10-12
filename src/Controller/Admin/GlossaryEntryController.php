<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Manuxi\SuluGlossaryBundle\Application\GlossaryEntryTranslationRemover;
use Manuxi\SuluGlossaryBundle\Domain\Event\CopiedLanguageEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\CreatedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\DraftRemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\ModifiedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\VersionRestoredEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Exception\GlossaryEntryNotFoundException;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Content\Application\ContentHash\ContentHashChecker;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

#[Route('/admin/api')]
class GlossaryEntryController implements SecuredControllerInterface
{
    /**
     * Permissions which the request method based check of the security listener does not cover.
     */
    private const ACTION_PERMISSIONS = [
        WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH => PermissionTypes::LIVE,
        WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH => PermissionTypes::LIVE,
        WorkflowInterface::WORKFLOW_TRANSITION_REMOVE_DRAFT => PermissionTypes::LIVE,
    ];

    private const NORMALIZER_CONTEXT = [
        'sulu_admin' => true,
        'sulu_admin_glossary_entry' => true,
        'sulu_admin_glossary_entry_content' => true,
    ];

    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly NormalizerInterface $normalizer,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly ContentHashChecker $contentHashChecker,
        private readonly EntityManagerInterface $entityManager,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly TrashManagerInterface $trashManager,
        private readonly GlossaryEntryTranslationRemover $translationRemover,
        private readonly bool $isSingleLocale = false,
    ) {
    }

    #[Route(
        path: '/glossary-entries.{_format}',
        name: 'sulu_glossary.get_glossary_entries',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['GET']
    )]
    public function cgetAction(Request $request): Response
    {
        $hasFilterOrSearch = $request->query->has('search') || !empty($request->query->all('filter'));

        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(GlossaryEntry::RESOURCE_KEY);

        if ($hasFilterOrSearch || $this->isSingleLocale) {
            foreach ($fieldDescriptors as $name => $fieldDescriptor) {
                $fieldDescriptors[$name] = $this->fieldDescriptorFactory->excludeCaseFieldDescriptor($fieldDescriptor, 'ghostDimensionContent');
            }
            unset($fieldDescriptors['ghostLocale']);
        }

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(GlossaryEntry::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $idField = $fieldDescriptors['id'];
        Assert::isInstanceOf($idField, DoctrineFieldDescriptorInterface::class);
        $listBuilder->setIdField($idField);
        $listBuilder->addSelectField($fieldDescriptors['locale']);
        $listBuilder->addSelectField($fieldDescriptors['published']);
        $listBuilder->addSelectField($fieldDescriptors['publishedState']);
        $listBuilder->addSelectField($fieldDescriptors['workflowPlace']);

        if (isset($fieldDescriptors['ghostLocale'])) {
            $listBuilder->addSelectField($fieldDescriptors['ghostLocale']);
        }
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $types = array_filter(explode(',', $request->query->getString('types')));
        if ([] !== $types) {
            $listBuilder->in($fieldDescriptors['type'], $types);
        }

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            GlossaryEntry::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        /** @var array{_embedded: array{glossary_entries: mixed[][]}} $list */
        $list = $listRepresentation->toArray();
        foreach ($list['_embedded'][GlossaryEntry::RESOURCE_KEY] as &$item) {
            $item['publishedState'] = WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === ($item['publishedState'] ?? null);
        }

        return new JsonResponse($this->normalizer->normalize($list, 'json', self::NORMALIZER_CONTEXT));
    }

    #[Route(
        path: '/glossary-entries/{id}/versions.{_format}',
        name: 'sulu_glossary.get_glossary_entry_versions',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['GET']
    )]
    public function getVersionsAction(Request $request, string $id): Response
    {
        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(GlossaryEntry::VERSIONS_RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(GlossaryEntry::class);
        $listBuilder->setParameter('locale', $this->getLocale($request));
        $listBuilder->setParameter('id', $id);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $listBuilder->sort($fieldDescriptors['version'], 'DESC');
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            GlossaryEntry::VERSIONS_RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize($listRepresentation->toArray(), 'json'));
    }

    #[Route(
        path: '/glossary-entries/{id}.{_format}',
        name: 'sulu_glossary.get_glossary_entry',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['GET']
    )]
    public function getAction(Request $request, string $id): Response
    {
        $dimensionAttributes = $this->getDimensionAttributes($request);

        $version = $request->query->getInt('version');
        if ($version > 0) {
            $dimensionAttributes['version'] = $version;
        }

        try {
            $glossaryEntry = $this->repository->getOneBy(
                array_merge(['uuid' => $id, 'loadGhost' => true], $dimensionAttributes),
                [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_ADMIN => true]
            );
        } catch (GlossaryEntryNotFoundException $exception) {
            return new JsonResponse((new EntityNotFoundException($exception->getModel(), $id, $exception))->toArray(), 404);
        }

        $dimensionContent = $this->contentManager->resolve($glossaryEntry, $dimensionAttributes);

        return new JsonResponse($this->normalizer->normalize(
            $this->contentManager->normalize($dimensionContent),
            'json',
            self::NORMALIZER_CONTEXT
        ));
    }

    #[Route(
        path: '/glossary-entries.{_format}',
        name: 'sulu_glossary.post_glossary_entry',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['POST']
    )]
    public function postAction(Request $request): Response
    {
        $this->checkActionPermission($request);

        $data = $this->getData($request);
        $locale = $this->getLocale($request);

        $glossaryEntry = $this->repository->createNew();
        $this->repository->add($glossaryEntry);

        $dimensionContent = $this->contentManager->persist($glossaryEntry, $data, ['locale' => $locale]);
        $this->domainEventCollector->collect(new CreatedEvent($glossaryEntry->getUuid(), $dimensionContent->getTitle(), $locale, $data));
        $this->entityManager->flush();

        $this->handleAction($request, $glossaryEntry);

        return $this->getAction($request, $glossaryEntry->getUuid())->setStatusCode(201);
    }

    #[Route(
        path: '/glossary-entries/{id}.{_format}',
        name: 'sulu_glossary.put_glossary_entry',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['PUT']
    )]
    public function putAction(Request $request, string $id): Response
    {
        $this->checkActionPermission($request);

        $data = $this->getData($request);
        $locale = $this->getLocale($request);

        try {
            $glossaryEntry = $this->repository->getOneBy(
                ['uuid' => $id, 'locale' => $locale],
                [
                    GlossaryEntryRepositoryInterface::SELECT_GLOSSARY_ENTRY_CONTENT => [
                        'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                        'dimensionAttributes' => [
                            'locale' => $locale,
                            'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                        ],
                    ],
                ]
            );
        } catch (GlossaryEntryNotFoundException $exception) {
            return new JsonResponse((new EntityNotFoundException($exception->getModel(), $id, $exception))->toArray(), 404);
        }

        $this->contentHashChecker->checkHash(
            $data,
            $glossaryEntry,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
            $id
        );

        $dimensionContent = $this->contentManager->persist($glossaryEntry, $data, ['locale' => $locale]);
        $this->domainEventCollector->collect(new ModifiedEvent($id, $dimensionContent->getTitle(), $locale, $data));
        $this->entityManager->flush();

        $this->handleAction($request, $glossaryEntry);

        return $this->getAction($request, $id);
    }

    #[Route(
        path: '/glossary-entries/{id}.{_format}',
        name: 'sulu_glossary.post_glossary_entry_trigger',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['POST']
    )]
    public function postTriggerAction(Request $request, string $id): Response
    {
        $this->checkActionPermission($request);

        try {
            $glossaryEntry = $this->repository->getOneBy(['uuid' => $id]);
        } catch (GlossaryEntryNotFoundException $exception) {
            return new JsonResponse((new EntityNotFoundException($exception->getModel(), $id, $exception))->toArray(), 404);
        }

        $this->handleAction($request, $glossaryEntry);

        return $this->getAction($request, $id);
    }

    #[Route(
        path: '/glossary-entries/{id}.{_format}',
        name: 'sulu_glossary.delete_glossary_entry',
        options: ['expose' => true],
        defaults: ['_format' => 'json'],
        methods: ['DELETE']
    )]
    public function deleteAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);

        try {
            $glossaryEntry = $this->repository->getOneBy(['uuid' => $id]);
        } catch (GlossaryEntryNotFoundException $exception) {
            return new JsonResponse((new EntityNotFoundException($exception->getModel(), $id, $exception))->toArray(), 404);
        }

        if ($request->query->getBoolean('deleteLocale', false)) {
            $this->translationRemover->remove($glossaryEntry, $locale);
            $this->entityManager->flush();

            return new Response('', 204);
        }

        $title = $this->findTitle($glossaryEntry, $locale);
        $availableLocales = $this->findAvailableLocales($glossaryEntry);

        $this->trashManager->store(GlossaryEntry::RESOURCE_KEY, $glossaryEntry);
        $this->repository->remove($glossaryEntry);
        $this->domainEventCollector->collect(new RemovedEvent($id, $title, $locale, [], ['locales' => $availableLocales]));
        $this->entityManager->flush();

        return new Response('', 204);
    }

    public function getSecurityContext(): string
    {
        return GlossaryEntry::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @return array<string, mixed>
     */
    private function getDimensionAttributes(Request $request): array
    {
        return [
            'locale' => $this->getLocale($request),
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(Request $request): array
    {
        $data = array_replace($request->request->all(), ['locale' => $this->getLocale($request)]);

        if ($request->query->getBoolean('force', false)) {
            unset($data['_hash']);
        }

        return $data;
    }

    private function handleAction(Request $request, GlossaryEntry $glossaryEntry): void
    {
        $action = $request->query->getString('action');
        if ('' === $action || 'draft' === $action) {
            return;
        }

        $locale = $this->getLocale($request);
        $id = $glossaryEntry->getUuid();
        $title = $this->findTitle($glossaryEntry, $locale);

        switch ($action) {
            case 'copy_locale':
                $source = $request->query->getString('src', $locale);
                $destination = $request->query->getString('dest');

                $this->contentManager->copy(
                    $glossaryEntry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $source],
                    $glossaryEntry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $destination]
                );
                $this->domainEventCollector->collect(new CopiedLanguageEvent($id, $title, $destination, ['source' => $source]));
                break;

            case 'restore':
                $version = $request->query->getInt('version');
                if ($version <= 0) {
                    throw new InvalidArgumentException('The "version" query parameter is required for restoring a version.');
                }

                $this->contentManager->copy(
                    $glossaryEntry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $locale, 'version' => $version],
                    $glossaryEntry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $locale, 'version' => DimensionContentInterface::CURRENT_VERSION],
                    ['ignoredAttributes' => ['url']]
                );
                $this->domainEventCollector->collect(new VersionRestoredEvent($id, $title, $locale, ['version' => $version]));
                break;

            case WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH:
                $this->applyTransition($glossaryEntry, $locale, WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
                $this->domainEventCollector->collect(new PublishedEvent($id, $title, $locale));
                break;

            case WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH:
                $this->applyTransition($glossaryEntry, $locale, WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH);
                $this->domainEventCollector->collect(new UnpublishedEvent($id, $title, $locale));
                break;

            case WorkflowInterface::WORKFLOW_TRANSITION_REMOVE_DRAFT:
                $this->applyTransition($glossaryEntry, $locale, WorkflowInterface::WORKFLOW_TRANSITION_REMOVE_DRAFT);
                $this->domainEventCollector->collect(new DraftRemovedEvent($id, $title, $locale));
                break;

            default:
                $this->applyTransition($glossaryEntry, $locale, $action);
        }

        $this->entityManager->flush();
    }

    private function applyTransition(GlossaryEntry $glossaryEntry, string $locale, string $transition): void
    {
        $this->contentWorkflow->apply($glossaryEntry, ['locale' => $locale], $transition);
    }

    private function findTitle(GlossaryEntry $glossaryEntry, string $locale): ?string
    {
        foreach ($glossaryEntry->getDimensionContents() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale && null !== $dimensionContent->getTitle()) {
                return $dimensionContent->getTitle();
            }
        }

        foreach ($glossaryEntry->getDimensionContents() as $dimensionContent) {
            if (null !== $dimensionContent->getTitle()) {
                return $dimensionContent->getTitle();
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function findAvailableLocales(GlossaryEntry $glossaryEntry): array
    {
        /** @var GlossaryEntryDimensionContent $dimensionContent */
        foreach ($glossaryEntry->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale() && DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()) {
                return $dimensionContent->getAvailableLocales() ?? [];
            }
        }

        return [];
    }

    private function checkActionPermission(Request $request): void
    {
        $permission = self::ACTION_PERMISSIONS[$request->query->getString('action')] ?? null;
        if (null === $permission) {
            return;
        }

        $this->securityChecker->checkPermission(
            new SecurityCondition(GlossaryEntry::SECURITY_CONTEXT, $this->getLocale($request)),
            $permission,
        );
    }
}
