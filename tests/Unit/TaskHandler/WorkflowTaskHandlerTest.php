<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\TaskHandler;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\TaskHandler\AbstractWorkflowTaskHandler;
use Manuxi\SuluGlossaryBundle\TaskHandler\GlossaryEntryPublishTaskHandler;
use Manuxi\SuluGlossaryBundle\TaskHandler\GlossaryEntryUnpublishTaskHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkflowTaskHandlerTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentWorkflowInterface&MockObject $contentWorkflow;
    private DomainEventCollectorInterface&MockObject $collector;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->contentWorkflow = $this->createMock(ContentWorkflowInterface::class);
        $this->collector = $this->createMock(DomainEventCollectorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    /**
     * @param class-string<AbstractWorkflowTaskHandler> $class
     */
    private function createHandler(string $class): AbstractWorkflowTaskHandler
    {
        return new $class($this->repository, $this->contentWorkflow, $this->collector, $this->entityManager);
    }

    public function testPublishAppliesThePublishTransition(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->expects($this->once())->method('getOneWithContent')->with('uuid-1', 'en')->willReturn($entry);
        $this->contentWorkflow->expects($this->once())->method('apply')->with($entry, ['locale' => 'en'], 'publish');
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(PublishedEvent::class));
        $this->entityManager->expects($this->once())->method('flush');

        $this->createHandler(GlossaryEntryPublishTaskHandler::class)->handle(['id' => 'uuid-1', 'locale' => 'en']);
    }

    public function testUnpublishAppliesTheUnpublishTransition(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('getOneWithContent')->willReturn($entry);
        $this->contentWorkflow->expects($this->once())->method('apply')->with($entry, ['locale' => 'de'], 'unpublish');
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(UnpublishedEvent::class));
        $this->entityManager->expects($this->once())->method('flush');

        $this->createHandler(GlossaryEntryUnpublishTaskHandler::class)->handle(['id' => 'uuid-1', 'locale' => 'de']);
    }

    public function testSupportsOnlyGlossaryEntries(): void
    {
        $handler = $this->createHandler(GlossaryEntryPublishTaskHandler::class);

        $this->assertTrue($handler->supports(GlossaryEntry::class));
        $this->assertFalse($handler->supports(stdClass::class));
    }

    public function testConfigurationTitles(): void
    {
        $this->assertSame('sulu_content.task_handler.publish', $this->createHandler(GlossaryEntryPublishTaskHandler::class)->getConfiguration()->getTitle());
        $this->assertSame('sulu_content.task_handler.unpublish', $this->createHandler(GlossaryEntryUnpublishTaskHandler::class)->getConfiguration()->getTitle());
    }

    public function testWorkloadRequiresClassIdAndLocale(): void
    {
        $resolver = $this->createHandler(GlossaryEntryPublishTaskHandler::class)->configureOptionsResolver(new OptionsResolver());

        $this->assertSame(['class' => 'x', 'id' => 'y', 'locale' => 'en'], $resolver->resolve(['class' => 'x', 'id' => 'y', 'locale' => 'en']));

        $this->expectException(MissingOptionsException::class);
        $resolver->resolve(['class' => 'x']);
    }
}
