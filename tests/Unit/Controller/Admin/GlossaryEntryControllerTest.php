<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Manuxi\SuluGlossaryBundle\Application\GlossaryEntryTranslationRemover;
use Manuxi\SuluGlossaryBundle\Controller\Admin\GlossaryEntryController;
use Manuxi\SuluGlossaryBundle\Domain\Event\CopiedLanguageEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\VersionRestoredEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Exception\GlossaryEntryNotFoundException;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Content\Application\ContentHash\ContentHashChecker;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class GlossaryEntryControllerTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private NormalizerInterface&MockObject $normalizer;
    private ContentManagerInterface&MockObject $contentManager;
    private ContentWorkflowInterface&MockObject $contentWorkflow;
    private EntityManagerInterface&MockObject $entityManager;
    private SecurityCheckerInterface&MockObject $securityChecker;
    private DomainEventCollectorInterface&MockObject $collector;
    private TrashManagerInterface&MockObject $trashManager;
    private GlossaryEntryTranslationRemover&MockObject $translationRemover;
    private GlossaryEntryController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->contentWorkflow = $this->createMock(ContentWorkflowInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $this->collector = $this->createMock(DomainEventCollectorInterface::class);
        $this->trashManager = $this->createMock(TrashManagerInterface::class);
        $this->translationRemover = $this->createMock(GlossaryEntryTranslationRemover::class);

        $this->normalizer->method('normalize')->willReturnArgument(0);

        $this->controller = new GlossaryEntryController(
            $this->repository,
            $this->normalizer,
            $this->contentManager,
            $this->contentWorkflow,
            $this->createMock(ContentHashChecker::class),
            $this->entityManager,
            $this->createMock(FieldDescriptorFactoryInterface::class),
            $this->createMock(DoctrineListBuilderFactoryInterface::class),
            $this->createMock(RestHelperInterface::class),
            $this->securityChecker,
            $this->collector,
            $this->trashManager,
            $this->translationRemover,
        );
    }

    public function testSecurityContextAndLocale(): void
    {
        $this->assertSame('sulu.glossary.entries', $this->controller->getSecurityContext());
        $this->assertSame('de', $this->controller->getLocale(new Request(['locale' => 'de'])));
    }

    public function testGetReturnsNotFoundForUnknownEntry(): void
    {
        $this->repository->method('getOneBy')->willThrowException(new GlossaryEntryNotFoundException(['uuid' => 'x']));

        $response = $this->controller->getAction(new Request(['locale' => 'en']), 'x');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testGetReturnsNormalizedContent(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $dimensionContent = $entry->createDimensionContent();
        $this->repository->expects($this->once())->method('getOneBy')->with(
            $this->callback(static fn (array $filters): bool => 'uuid-1' === $filters['uuid'] && 'en' === $filters['locale'] && true === $filters['loadGhost'] && 'draft' === $filters['stage'])
        )->willReturn($entry);
        $this->contentManager->method('resolve')->with($entry, ['locale' => 'en', 'stage' => 'draft'])->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->with($dimensionContent)->willReturn(['id' => 'uuid-1', 'title' => 'WHO']);

        $response = $this->controller->getAction(new Request(['locale' => 'en']), 'uuid-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 'uuid-1', 'title' => 'WHO'], json_decode((string) $response->getContent(), true));
    }

    public function testGetPassesVersionToTheDimensionAttributes(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneBy')->willReturn($entry);
        $this->contentManager->expects($this->once())->method('resolve')
            ->with($entry, ['locale' => 'en', 'stage' => 'draft', 'version' => 5])
            ->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn([]);

        $this->controller->getAction(new Request(['locale' => 'en', 'version' => '5']), 'uuid-1');
    }

    public function testPutReturnsNotFoundForUnknownEntry(): void
    {
        $this->repository->method('getOneWithContent')->willThrowException(new GlossaryEntryNotFoundException(['uuid' => 'x']));

        $this->assertSame(404, $this->controller->putAction(new Request(['locale' => 'en']), 'x')->getStatusCode());
    }

    public function testPostTriggerReturnsNotFoundForUnknownEntry(): void
    {
        $this->repository->method('getOneWithContent')->willThrowException(new GlossaryEntryNotFoundException(['uuid' => 'x']));

        $this->assertSame(404, $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'publish']), 'x')->getStatusCode());
    }

    public function testPublishRequiresLivePermission(): void
    {
        $this->securityChecker->expects($this->once())->method('checkPermission')->with(
            $this->callback(static fn (SecurityCondition $condition): bool => GlossaryEntry::SECURITY_CONTEXT === $condition->getSecurityContext() && 'en' === $condition->getLocale()),
            PermissionTypes::LIVE
        );
        $this->repository->method('getOneWithContent')->willThrowException(new GlossaryEntryNotFoundException(['uuid' => 'x']));

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'publish']), 'x');
    }

    public function testPublishAppliesTheTransitionAndCollectsAnEvent(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneWithContent')->willReturn($entry);
        $this->contentManager->method('resolve')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn([]);

        $this->contentWorkflow->expects($this->once())->method('apply')->with($entry, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(PublishedEvent::class));
        $this->entityManager->expects($this->once())->method('flush');

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'publish']), 'uuid-1');
    }

    public function testCopyLocaleCopiesTheDraftAndCollectsAnEvent(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneWithContent')->willReturn($entry);
        $this->contentManager->method('resolve')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn([]);

        $this->contentManager->expects($this->once())->method('copy')->with(
            $entry,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'en'],
            $entry,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'de']
        );
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(CopiedLanguageEvent::class));

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'copy_locale', 'src' => 'en', 'dest' => 'de']), 'uuid-1');
    }

    public function testRestoreCopiesTheVersionIntoTheDraft(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneWithContent')->willReturn($entry);
        $this->contentManager->method('resolve')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn([]);

        $this->contentManager->expects($this->once())->method('copy')->with(
            $entry,
            ['stage' => 'draft', 'locale' => 'en', 'version' => 1700000000],
            $entry,
            ['stage' => 'draft', 'locale' => 'en', 'version' => DimensionContentInterface::CURRENT_VERSION],
            ['ignoredAttributes' => ['url']]
        );
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(VersionRestoredEvent::class));

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'restore', 'version' => '1700000000']), 'uuid-1');
    }

    public function testRestoreLoadsTheRequestedVersion(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->expects($this->once())->method('getOneWithContent')->with('uuid-1', 'en', 1700000000)->willReturn($entry);
        $this->contentManager->method('copy')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('resolve')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn([]);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'restore', 'version' => '1700000000']), 'uuid-1');
    }

    public function testRestoreWithoutVersionFails(): void
    {
        $this->repository->method('getOneWithContent')->willReturn(new GlossaryEntry('uuid-1'));

        $this->expectException(InvalidArgumentException::class);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'restore']), 'uuid-1');
    }

    public function testDeleteLocaleDelegatesToTheTranslationRemover(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneBy')->willReturn($entry);
        $this->translationRemover->expects($this->once())->method('remove')->with($entry, 'de');
        $this->repository->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteAction(new Request(['locale' => 'de', 'deleteLocale' => 'true']), 'uuid-1');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDeleteStoresTheEntryInTheTrashAndRemovesIt(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setTemplateData(['title' => 'WHO']);
        $entry->addDimensionContent($dimensionContent);
        $this->repository->method('getOneBy')->willReturn($entry);

        $this->trashManager->expects($this->once())->method('store')->with(GlossaryEntry::RESOURCE_KEY, $entry);
        $this->repository->expects($this->once())->method('remove')->with($entry);
        $this->collector->expects($this->once())->method('collect')->with($this->callback(
            static fn (RemovedEvent $event): bool => 'WHO' === $event->getResourceTitle() && 'uuid-1' === $event->getResourceId()
        ));
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteAction(new Request(['locale' => 'en']), 'uuid-1');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDeleteReturnsNotFoundForUnknownEntry(): void
    {
        $this->repository->method('getOneBy')->willThrowException(new GlossaryEntryNotFoundException(['uuid' => 'x']));

        $this->assertSame(404, $this->controller->deleteAction(new Request(['locale' => 'en']), 'x')->getStatusCode());
    }
}
