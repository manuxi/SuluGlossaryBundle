<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Entity;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use PHPUnit\Framework\TestCase;

class GlossaryEntryTest extends TestCase
{
    public function testGeneratesUuidV7WhenNoneGiven(): void
    {
        $entry = new GlossaryEntry();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $entry->getUuid());
        $this->assertSame($entry->getUuid(), $entry->getId());
    }

    public function testKeepsGivenUuid(): void
    {
        $entry = new GlossaryEntry('11111111-1111-7111-8111-111111111111');

        $this->assertSame('11111111-1111-7111-8111-111111111111', $entry->getUuid());
    }

    public function testCreatesDimensionContentForItself(): void
    {
        $entry = new GlossaryEntry();

        $dimensionContent = $entry->createDimensionContent();

        $this->assertInstanceOf(GlossaryEntryDimensionContent::class, $dimensionContent);
        $this->assertSame($entry, $dimensionContent->getResource());
    }

    public function testAddsAndRemovesDimensionContents(): void
    {
        $entry = new GlossaryEntry();
        $dimensionContent = $entry->createDimensionContent();

        $entry->addDimensionContent($dimensionContent);
        $this->assertCount(1, $entry->getDimensionContents());

        $entry->removeDimensionContent($dimensionContent);
        $this->assertCount(0, $entry->getDimensionContents());
    }

    public function testConstants(): void
    {
        $this->assertSame('glossary_entries', GlossaryEntry::RESOURCE_KEY);
        $this->assertSame('glossary_entry', GlossaryEntry::TEMPLATE_TYPE);
        $this->assertSame('sulu.glossary.entries', GlossaryEntry::SECURITY_CONTEXT);
    }
}
