<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Trash;

use InvalidArgumentException;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\Trash\GlossaryEntryTrashItemHandler;
use PHPUnit\Framework\TestCase;
use stdClass;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;

class GlossaryEntryTrashItemHandlerTest extends TestCase
{
    private function createHandler(): GlossaryEntryTrashItemHandler
    {
        return new GlossaryEntryTrashItemHandler(
            $this->createMock(TrashItemRepositoryInterface::class),
            $this->createMock(GlossaryEntryRepositoryInterface::class),
            $this->createMock(ContentNormalizerInterface::class),
            $this->createMock(ContentMergerInterface::class),
            $this->createMock(ContentManagerInterface::class),
            $this->createMock(DomainEventCollectorInterface::class),
        );
    }

    public function testResourceKey(): void
    {
        $this->assertSame(GlossaryEntry::RESOURCE_KEY, GlossaryEntryTrashItemHandler::getResourceKey());
    }

    public function testRestoreConfigurationPointsToTheEditView(): void
    {
        $configuration = $this->createHandler()->getConfiguration();

        $this->assertSame(GlossaryEntryAdmin::EDIT_TABS_VIEW, $configuration->getView());
        $this->assertSame(['id' => 'id'], $configuration->getResultToView());
    }

    public function testStoreRequiresAGlossaryEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createHandler()->store(new stdClass());
    }

    public function testStoreRequiresAnUnlocalizedDimensionContent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createHandler()->store(new GlossaryEntry());
    }
}
