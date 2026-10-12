<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Entity;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use PHPUnit\Framework\TestCase;

class GlossaryEntrySettingsTest extends TestCase
{
    public function testDefaultsAreNull(): void
    {
        $settings = new GlossaryEntrySettings();

        $this->assertNull($settings->getId());
        $this->assertNull($settings->getToggleHeader());
        $this->assertNull($settings->getToggleHero());
        $this->assertNull($settings->getToggleBreadcrumbs());
        $this->assertNull($settings->getPageGlossary());
    }

    public function testSettersAreFluent(): void
    {
        $settings = (new GlossaryEntrySettings())
            ->setToggleHeader(true)
            ->setToggleHero(false)
            ->setToggleBreadcrumbs(true)
            ->setPageGlossary('page-uuid');

        $this->assertTrue($settings->getToggleHeader());
        $this->assertFalse($settings->getToggleHero());
        $this->assertTrue($settings->getToggleBreadcrumbs());
        $this->assertSame('page-uuid', $settings->getPageGlossary());
    }
}
