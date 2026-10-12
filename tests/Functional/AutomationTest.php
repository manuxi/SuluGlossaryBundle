<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\TaskHandler\GlossaryEntryPublishTaskHandler;
use Manuxi\SuluGlossaryBundle\TaskHandler\GlossaryEntryUnpublishTaskHandler;
use stdClass;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class AutomationTest extends SuluTestCase
{
    protected function setUp(): void
    {
        static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        static::ensureKernelShutdown();
    }

    public function testScheduledTasksPublishAndUnpublishTheEntry(): void
    {
        $client = static::createAuthenticatedClient();
        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en', [
            'template' => 'default', 'title' => 'Timed', 'url' => '/glossary/timed', 'explanation' => 'x',
        ]);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        static::ensureKernelShutdown();

        static::bootKernel();
        /** @var GlossaryEntryPublishTaskHandler $publish */
        $publish = static::getContainer()->get(GlossaryEntryPublishTaskHandler::class);
        $publish->handle(['id' => $id, 'locale' => 'en']);
        $this->assertSame('published', $this->workflowPlace($id));

        /** @var GlossaryEntryUnpublishTaskHandler $unpublish */
        $unpublish = static::getContainer()->get(GlossaryEntryUnpublishTaskHandler::class);
        $unpublish->handle(['id' => $id, 'locale' => 'en']);
        $this->assertSame('unpublished', $this->workflowPlace($id));
    }

    private function workflowPlace(string $id): ?string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        /** @var GlossaryEntryRepositoryInterface $repository */
        $repository = static::getContainer()->get(GlossaryEntryRepositoryInterface::class);
        $dimensionContent = $repository->getOneBy(['uuid' => $id])->getDimensionContents()->filter(
            static fn ($dimensionContent): bool => 'en' === $dimensionContent->getLocale() && 'draft' === $dimensionContent->getStage() && 0 === $dimensionContent->getVersion()
        )->first();

        return false === $dimensionContent ? null : $dimensionContent->getWorkflowPlace();
    }

    public function testAdminOffersTheAutomationTab(): void
    {
        static::bootKernel();
        /** @var GlossaryEntryAdmin $admin */
        $admin = static::getContainer()->get(GlossaryEntryAdmin::class);
        $views = new ViewCollection();

        $admin->configureViews($views);

        $this->assertTrue($views->has(GlossaryEntryAdmin::EDIT_AUTOMATION_VIEW));
    }

    public function testHandlersAreConfiguredForGlossaryEntries(): void
    {
        static::bootKernel();
        /** @var GlossaryEntryPublishTaskHandler $publish */
        $publish = static::getContainer()->get(GlossaryEntryPublishTaskHandler::class);

        $this->assertTrue($publish->supports(GlossaryEntry::class));
        $this->assertFalse($publish->supports(stdClass::class));
    }
}
