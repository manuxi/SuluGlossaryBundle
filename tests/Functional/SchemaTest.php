<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;

class SchemaTest extends GlossaryKernelTestCase
{
    public function testMappingIsValid(): void
    {
        $validator = new SchemaValidator($this->getEntityManager());

        foreach ([GlossaryEntry::class, GlossaryEntryDimensionContent::class, GlossaryEntrySettings::class] as $class) {
            $this->assertSame([], $validator->validateClass($this->getEntityManager()->getClassMetadata($class)), $class);
        }
    }

    public function testTablesExist(): void
    {
        $tables = $this->getEntityManager()->getConnection()->createSchemaManager()->listTableNames();

        $this->assertContains('glossary_entries', $tables);
        $this->assertContains('glossary_entry_dimension_contents', $tables);
        $this->assertContains('glossary_entry_settings', $tables);
    }
}
