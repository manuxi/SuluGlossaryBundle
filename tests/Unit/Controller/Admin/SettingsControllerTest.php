<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Manuxi\SuluGlossaryBundle\Controller\Admin\SettingsController;
use Manuxi\SuluGlossaryBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Symfony\Component\HttpFoundation\Request;

class SettingsControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DomainEventCollectorInterface&MockObject $collector;
    /** @var EntityRepository<GlossaryEntrySettings>&MockObject */
    private EntityRepository&MockObject $repository;
    private SettingsController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->collector = $this->createMock(DomainEventCollectorInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(GlossaryEntrySettings::class)->willReturn($this->repository);

        $this->controller = new SettingsController($this->entityManager, $this->collector);
    }

    public function testGetReturnsEmptySettingsWhenNothingIsStored(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $data = json_decode((string) $this->controller->getAction()->getContent(), true);

        $this->assertSame(['toggleHeader' => null, 'toggleHero' => null, 'toggleBreadcrumbs' => null, 'pageGlossary' => null], $data);
    }

    public function testGetReturnsStoredSettings(): void
    {
        $this->repository->method('findOneBy')->willReturn((new GlossaryEntrySettings())->setToggleHero(true)->setPageGlossary('uuid'));

        $data = json_decode((string) $this->controller->getAction()->getContent(), true);

        $this->assertTrue($data['toggleHero']);
        $this->assertSame('uuid', $data['pageGlossary']);
    }

    public function testPutCreatesSettingsWhenNoneExist(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(GlossaryEntrySettings::class));
        $this->entityManager->expects($this->once())->method('flush');
        $this->collector->expects($this->once())->method('collect')->with($this->isInstanceOf(ModifiedEvent::class));

        $request = new Request([], [], [], [], [], [], (string) json_encode(['toggleHeader' => true, 'pageGlossary' => 'abc']));
        $data = json_decode((string) $this->controller->putAction($request)->getContent(), true);

        $this->assertTrue($data['toggleHeader']);
        $this->assertSame('abc', $data['pageGlossary']);
        $this->assertNull($data['toggleHero']);
    }

    public function testPutUpdatesExistingSettingsWithoutPersisting(): void
    {
        $settings = (new GlossaryEntrySettings())->setToggleHeader(true);
        $this->repository->method('findOneBy')->willReturn($settings);
        $this->entityManager->expects($this->never())->method('persist');

        $request = new Request([], [], [], [], [], [], (string) json_encode(['toggleHeader' => false]));
        $this->controller->putAction($request);

        $this->assertFalse($settings->getToggleHeader());
    }

    public function testSecurityContextAndLocale(): void
    {
        $this->assertSame('sulu.glossary.settings', $this->controller->getSecurityContext());
        $this->assertSame('de', $this->controller->getLocale(new Request(['locale' => 'de'])));
        $this->assertNull($this->controller->getLocale(new Request()));
    }
}
