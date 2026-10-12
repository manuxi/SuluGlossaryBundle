<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Content;

use Manuxi\SuluGlossaryBundle\Content\DataMapper\GlossaryEntryTypeDataMapper;
use Manuxi\SuluGlossaryBundle\Content\Merger\GlossaryEntryTypeMerger;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use PHPUnit\Framework\TestCase;
use stdClass;

class GlossaryEntryTypeMappingTest extends TestCase
{
    public function testDataMapperWritesTypeToBothDimensionContents(): void
    {
        $entry = new GlossaryEntry();
        $unlocalized = new GlossaryEntryDimensionContent($entry);
        $localized = new GlossaryEntryDimensionContent($entry);

        (new GlossaryEntryTypeDataMapper())->map($unlocalized, $localized, ['type' => 'acronym']);

        $this->assertSame('acronym', $unlocalized->getType());
        $this->assertSame('acronym', $localized->getType());
    }

    public function testDataMapperIgnoresDataWithoutType(): void
    {
        $entry = new GlossaryEntry();
        $unlocalized = new GlossaryEntryDimensionContent($entry);
        $localized = new GlossaryEntryDimensionContent($entry);
        $localized->setType('abbreviation');

        (new GlossaryEntryTypeDataMapper())->map($unlocalized, $localized, ['title' => 'x']);

        $this->assertSame('abbreviation', $localized->getType());
        $this->assertSame('term', $unlocalized->getType());
    }

    public function testDataMapperResetsInvalidTypeToDefault(): void
    {
        $entry = new GlossaryEntry();
        $unlocalized = new GlossaryEntryDimensionContent($entry);
        $localized = new GlossaryEntryDimensionContent($entry);

        (new GlossaryEntryTypeDataMapper())->map($unlocalized, $localized, ['type' => ['bad']]);

        $this->assertSame('term', $localized->getType());
    }

    public function testDataMapperIgnoresForeignDimensionContents(): void
    {
        $entry = new GlossaryEntry();
        $unlocalized = new GlossaryEntryDimensionContent($entry);
        $foreign = $this->createMock(\Sulu\Content\Domain\Model\DimensionContentInterface::class);

        (new GlossaryEntryTypeDataMapper())->map($unlocalized, $foreign, ['type' => 'acronym']);

        $this->assertSame('term', $unlocalized->getType());
    }

    public function testMergerCopiesTypeFromSourceToTarget(): void
    {
        $entry = new GlossaryEntry();
        $target = new GlossaryEntryDimensionContent($entry);
        $source = (new GlossaryEntryDimensionContent($entry))->setType('acronym');

        (new GlossaryEntryTypeMerger())->merge($target, $source);

        $this->assertSame('acronym', $target->getType());
    }

    public function testMergerIgnoresOtherObjects(): void
    {
        $target = new GlossaryEntryDimensionContent(new GlossaryEntry());

        (new GlossaryEntryTypeMerger())->merge($target, new stdClass());
        (new GlossaryEntryTypeMerger())->merge(new stdClass(), $target);

        $this->assertSame('term', $target->getType());
    }
}
