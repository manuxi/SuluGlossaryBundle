<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\TaskHandler;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractWorkflowTaskHandler implements AutomationTaskHandlerInterface
{
    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver
            ->setRequired(['class', 'id', 'locale'])
            ->setAllowedTypes('class', 'string')
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    public function supports(string $entityClass): bool
    {
        return GlossaryEntry::class === $entityClass;
    }

    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create($this->getTitle());
    }

    /**
     * @param array{id: string, locale: string} $workload
     */
    public function handle($workload): void
    {
        $glossaryEntry = $this->repository->getOneWithContent($workload['id'], $workload['locale']);

        $this->contentWorkflow->apply($glossaryEntry, ['locale' => $workload['locale']], $this->getTransition());
        $this->domainEventCollector->collect($this->createEvent($workload['id'], $workload['locale']));

        $this->entityManager->flush();
    }

    abstract protected function getTitle(): string;

    abstract protected function getTransition(): string;

    abstract protected function createEvent(string $id, string $locale): AbstractEvent;
}
