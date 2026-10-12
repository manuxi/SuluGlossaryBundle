<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use PHPUnit\Framework\TestCase;

class GlossaryEntryDimensionContentTest extends TestCase
{
    private GlossaryEntryDimensionContent $dimensionContent;

    protected function setUp(): void
    {
        $this->dimensionContent = new GlossaryEntryDimensionContent(new GlossaryEntry());
    }

    public function testDefaultTypeIsTerm(): void
    {
        $this->assertSame('term', $this->dimensionContent->getType());
    }

    public function testSetTypeAcceptsKnownTypes(): void
    {
        foreach (GlossaryEntryDimensionContent::TYPES as $type) {
            $this->dimensionContent->setType($type);
            $this->assertSame($type, $this->dimensionContent->getType());
        }
    }

    public function testSetTypeFallsBackToDefaultForUnknownOrNullValues(): void
    {
        $this->dimensionContent->setType('acronym');
        $this->dimensionContent->setType('unknown');
        $this->assertSame('term', $this->dimensionContent->getType());

        $this->dimensionContent->setType('abbreviation');
        $this->assertSame('abbreviation', $this->dimensionContent->getType());
        $this->dimensionContent->setType(null);
        $this->assertSame('term', $this->dimensionContent->getType());
    }

    public function testTemplateDataFillsTitleAndExplanation(): void
    {
        $this->dimensionContent->setTemplateData(['title' => 'WHO', 'explanation' => 'World Health Organization', 'other' => 1]);

        $this->assertSame('WHO', $this->dimensionContent->getTitle());
        $this->assertSame('World Health Organization', $this->dimensionContent->getExplanation());
        $this->assertSame(1, $this->dimensionContent->getTemplateData()['other']);
    }

    public function testTemplateDataIgnoresNonStringValues(): void
    {
        $this->dimensionContent->setTemplateData(['title' => 'WHO']);
        $this->dimensionContent->setTemplateData(['title' => 5, 'explanation' => ['x']]);

        $this->assertNull($this->dimensionContent->getTitle());
        $this->assertNull($this->dimensionContent->getExplanation());
    }

    public function testTemplateDataWithoutKeysKeepsExistingValues(): void
    {
        $this->dimensionContent->setTemplateData(['title' => 'WHO']);
        $this->dimensionContent->setTemplateData(['other' => 'value']);

        $this->assertSame('WHO', $this->dimensionContent->getTitle());
    }

    public function testStaticKeys(): void
    {
        $this->assertSame('glossary_entry', GlossaryEntryDimensionContent::getTemplateType());
        $this->assertSame('glossary_entries', GlossaryEntryDimensionContent::getResourceKey());
    }

    public function testResourceIsTheGlossaryEntry(): void
    {
        $entry = new GlossaryEntry();

        $this->assertSame($entry, (new GlossaryEntryDimensionContent($entry))->getResource());
    }

    public function testTimestampsAreImmutableAndInitialized(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->dimensionContent->getCreated());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->dimensionContent->getChanged());
    }
}
